<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __invoke(Request $request, string $tenant): View
    {
        $tenantModel = Tenant::where('slug', $tenant)
            ->firstOrFail();

        return view('pages.booking', [
            'tenant' => $tenantModel,
        ]);
    }
}
