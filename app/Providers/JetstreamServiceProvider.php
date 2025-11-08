<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Laravel\Jetstream\Jetstream;
use App\Livewire\UpdateProfileInformationForm;
use App\Livewire\UpdatePasswordForm;
use Livewire;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        //Jetstream::createTeamsUsing(CreateTeam::class);
        //Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        //Jetstream::addTeamMembersUsing(AddTeamMember::class);
        //Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        //Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        //Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
        Livewire::component('profile.update-profile-information-form', UpdateProfileInformationForm::class);
        Livewire::component('profile.update-password-form', UpdatePasswordForm::class);

    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::role(User::ROLE_ADMIN, User::ROLE_LABELS[User::ROLE_ADMIN], [
            'create',
            'read',
            'update',
            'delete',
        ])->description('Administrators can perform any action, including managing users.');

        Jetstream::role(User::ROLE_EMPLOYEE_MANAGER, User::ROLE_LABELS[User::ROLE_EMPLOYEE_MANAGER], [
            'read',
            'create',
            'update',
        ])->description('Managers can read, create, and update records within their organization.');

        Jetstream::role(User::ROLE_EMPLOYEE_STANDARD, User::ROLE_LABELS[User::ROLE_EMPLOYEE_STANDARD], [
            'read',
            'work',
        ])->description('Standard employees can work assigned jobs and submit logs.');

        Jetstream::role(User::ROLE_CUSTOMER, User::ROLE_LABELS[User::ROLE_CUSTOMER], [
            'view_invoices',
        ])->description('Customers can view and comment on their invoices.');
    }
}
