<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Validator::make($data, [
            'role' => ['required', 'in:'.implode(',', array_keys(UserResource::assignableRoleOptions()))],
            'services' => [UserResource::tenantServiceRule()],
        ])->validate();

        if ($this->record->is(Auth::user()) && $data['role'] !== $this->record->role->value) {
            throw ValidationException::withMessages([
                'data.role' => 'You cannot change your own role.',
            ]);
        }

        return $data;
    }
}
