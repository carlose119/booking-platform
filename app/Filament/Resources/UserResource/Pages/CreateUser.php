<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Validator;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Validator::make($data, [
            'role' => ['required', 'in:'.implode(',', array_keys(UserResource::assignableRoleOptions()))],
        ])->validate();

        $data['tenant_id'] = UserResource::activeTenantId();

        return $data;
    }
}
