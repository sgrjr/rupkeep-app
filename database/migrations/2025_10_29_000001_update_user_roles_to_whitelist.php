<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->mapRoles([
            'administrator' => 'admin',
            'editor' => 'employee_manager',
            'viewer' => 'employee_standard',
            'driver' => 'employee_standard',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->mapRoles([
            'admin' => 'administrator',
            'employee_manager' => 'editor',
            'employee_standard' => 'driver',
        ]);
    }

    private function mapRoles(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('users')
                ->where('organization_role', $from)
                ->update(['organization_role' => $to]);
        }
    }
};

