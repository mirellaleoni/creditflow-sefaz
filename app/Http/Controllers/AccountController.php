<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Consulta o saldo da conta do usuário autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        return response()->json([
            'id' => $account->id,
            'balance_cents' => $account->balance_cents,
        ]);
    }
}
