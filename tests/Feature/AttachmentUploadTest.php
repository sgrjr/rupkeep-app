<?php

namespace Tests\Feature;

use App\Livewire\EditUserLog;
use App\Livewire\ShowPilotCarJob;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-454. A driver's upload could never succeed: the log page wrote to a
 * disk called `private` that was never configured. The job page wrote an
 * absolute path while the log page wrote a relative one, and the download
 * and delete code read `location` as a raw filesystem path, so one of the
 * two always broke. Both stored `{job folder}/{original name}`, so two
 * drivers uploading IMG_0001.jpg overwrote each other.
 */
class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private PilotCarJob $job;
    private UserLog $log;
    private User $admin;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake(Attachment::DISK);

        $this->organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-454',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '500.00',
        ]);

        $this->log = UserLog::create([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $this->driver->id,
            'approval_status' => 'confirmed',
        ]);
    }

    private function driverUploads(string $name = 'IMG_0001.jpg'): Attachment
    {
        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->set('file', UploadedFile::fake()->create($name, 120))
            ->call('uploadFile')
            ->assertHasNoErrors();

        return Attachment::latest('id')->firstOrFail();
    }

    public function test_a_driver_can_upload_a_receipt_photo_to_their_log(): void
    {
        $attachment = $this->driverUploads('IMG_0001.jpg');

        $this->assertSame(UserLog::class, $attachment->attachable_type);
        $this->assertSame($this->log->id, (int) $attachment->attachable_id);
        $this->assertSame('IMG_0001.jpg', $attachment->file_name, 'the name the driver gave is kept');
        $this->assertMatchesRegularExpression(
            '#^jobs/attachments_' . $this->job->id . '/[0-9a-f-]{36}\.jpg$#',
            $attachment->location,
            'stored under a uuid, relative to the private disk'
        );
        Storage::disk(Attachment::DISK)->assertExists($attachment->location);

        $this->actingAs($this->driver)
            ->get(route('attachments.download', $attachment))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=IMG_0001.jpg');
    }

    public function test_staff_uploads_on_the_job_page_are_stored_the_same_way(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->set('file', UploadedFile::fake()->create('permit.pdf', 300))
            ->call('uploadFile')
            ->assertHasNoErrors();

        $attachment = Attachment::latest('id')->firstOrFail();

        $this->assertSame(PilotCarJob::class, $attachment->attachable_type);
        $this->assertStringStartsWith('jobs/attachments_' . $this->job->id . '/', $attachment->location, 'relative, never storage_path()');
        $this->assertSame('permit.pdf', $attachment->file_name);
        Storage::disk(Attachment::DISK)->assertExists($attachment->location);

        $this->actingAs($this->driver)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_two_uploads_with_the_same_name_keep_both_files(): void
    {
        $first = $this->driverUploads('IMG_0001.jpg');
        $second = $this->driverUploads('IMG_0001.jpg');

        $this->assertNotSame($first->location, $second->location);
        Storage::disk(Attachment::DISK)->assertExists($first->location);
        Storage::disk(Attachment::DISK)->assertExists($second->location);

        // Deleting one takes only its own file.
        $this->actingAs($this->driver)->delete(route('attachments.destroy', $first))->assertRedirect();

        $this->assertNull(Attachment::withTrashed()->find($first->id), 'removal is final');
        Storage::disk(Attachment::DISK)->assertMissing($first->location);
        Storage::disk(Attachment::DISK)->assertExists($second->location);
    }

    public function test_only_photos_and_pdfs_are_accepted(): void
    {
        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->set('file', UploadedFile::fake()->create('setup.exe', 120))
            ->call('uploadFile')
            ->assertHasErrors(['file' => 'mimes']);

        $this->assertSame(0, Attachment::count());
        $this->assertSame([], Storage::disk(Attachment::DISK)->allFiles());
    }

    public function test_a_row_written_with_an_absolute_path_before_the_fix_still_downloads(): void
    {
        $relative = 'jobs/attachments_' . $this->job->id . '/route-sheet.pdf';
        Storage::disk(Attachment::DISK)->put($relative, '%PDF-1.4');

        $legacy = Attachment::create([
            'attachable_id' => $this->job->id,
            'attachable_type' => PilotCarJob::class,
            'location' => Storage::disk(Attachment::DISK)->path($relative),
            'organization_id' => $this->organization->id,
            'is_public' => false,
        ]);

        $this->assertSame($relative, $legacy->storagePath());
        $this->assertSame('route-sheet.pdf', $legacy->file_name, 'basename until the migration fills file_name');
        $this->assertTrue($legacy->fileExists());

        $this->actingAs($this->admin)->get(route('attachments.download', $legacy))->assertOk();
    }

    public function test_a_missing_file_is_a_404_not_a_500(): void
    {
        $gone = Attachment::create([
            'attachable_id' => $this->job->id,
            'attachable_type' => PilotCarJob::class,
            'location' => 'jobs/attachments_' . $this->job->id . '/lost.pdf',
            'file_name' => 'lost.pdf',
            'organization_id' => $this->organization->id,
            'is_public' => false,
        ]);

        $this->actingAs($this->admin)->get(route('attachments.download', $gone))->assertNotFound();
    }

    public function test_force_deleting_a_log_or_a_job_removes_their_files(): void
    {
        $onLog = $this->driverUploads('receipt.jpg');

        Livewire::actingAs($this->admin)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->set('file', UploadedFile::fake()->create('permit.pdf', 300))
            ->call('uploadFile');
        $onJob = Attachment::where('attachable_type', PilotCarJob::class)->firstOrFail();

        $this->log->forceDelete();
        Storage::disk(Attachment::DISK)->assertMissing($onLog->location);
        Storage::disk(Attachment::DISK)->assertExists($onJob->location);

        $this->job->forceDelete();
        Storage::disk(Attachment::DISK)->assertMissing($onJob->location);
        $this->assertSame(0, Attachment::withTrashed()->count());
    }
}
