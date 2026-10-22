<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data_and_gets_account_with_zero_balance(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Mirella Teste',
            'email' => 'mirella@example.com',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at'],
                'token',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'mirella@example.com']);
        $user = User::where('email', 'mirella@example.com')->first();
        $this->assertNotNull($user->account);
        $this->assertSame(0, $user->account->balance_cents);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicado@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Outro Usuário',
            'email' => 'duplicado@example.com',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'MinhaSenha123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'MinhaSenha123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_login_fails_with_incorrect_credentials(): void
    {
        User::factory()->create([
            'email' => 'correto@example.com',
            'password' => 'SenhaCorreta123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'correto@example.com',
            'password' => 'SenhaIncorreta999',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_protected_routes_cannot_be_accessed_without_token(): void
    {
        $response = $this->getJson('/api/account');
        $response->assertStatus(401);

        $response = $this->getJson('/api/transactions');
        $response->assertStatus(401);
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        // Logout revoga o token
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logout realizado.']);

        // O token foi excluído do banco
        $this->assertDatabaseEmpty('personal_access_tokens');

        // Limpa estado em memória da autenticação
        app('auth')->forgetGuards();

        // Acesso seguinte com o mesmo token deve retornar 401
        $subsequent = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $subsequent->assertStatus(401);
    }
}
