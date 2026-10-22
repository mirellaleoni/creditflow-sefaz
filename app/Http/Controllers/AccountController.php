<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Consulta o saldo da conta do usuário autenticado.
     */
    public function show(Request $request): AccountResource
    {
        return new AccountResource($request->user()->account);
    }
}
