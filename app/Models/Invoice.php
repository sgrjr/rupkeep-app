<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\Customer;
use App\Models\InvoiceComment;
use App\Models\Attachment;
use App\Models\UserLog;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'paid_in_full',
        'values',
        'organization_id',
        'customer_id',
        'pilot_car_job_id'
    ];

    public $timestamps = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_in_full' => 'boolean',
            'values' => 'array',
        ];
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function job(){
        return $this->belongsTo(PilotCarJob::class, 'pilot_car_job_id');
    }

    public function comments()
    {
        return $this->hasMany(InvoiceComment::class);
    }

    public function publicProofAttachments()
    {
        $job = $this->job;

        if (!$job) {
            return collect();
        }

        $attachments = $job->attachments()
            ->where('is_public', true)
            ->get();

        $logIds = $job->logs()->pluck('id');

        if ($logIds->isNotEmpty()) {
            $logAttachments = Attachment::query()
                ->where('is_public', true)
                ->where('attachable_type', UserLog::class)
                ->whereIn('attachable_id', $logIds)
                ->get();

            $attachments = $attachments->merge($logAttachments);
        }

        return $attachments;
    }

    public function getInvoiceNumberAttribute(){
        return substr($this->created_at,0,4) . str_pad((String)$this->id, 5, "0", STR_PAD_LEFT );
    }
}
