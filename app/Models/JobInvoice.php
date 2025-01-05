<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use App\Models\Customer;

class JobInvoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'pilot_car_job_id'
    ];

    public $timestamps = false;

    public $table = "jobs_invoices";

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }

    public function job(){
        return $this->belongsTo(PilotCarJob::class);
    }
}
