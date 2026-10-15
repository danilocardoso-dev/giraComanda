<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_request_password_reset_without_revealing_if_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'usuario@giracomanda.test']);

        $knownResponse = $this->post(route('password.email'), ['email' => $user->email]);
        $unknownResponse = $this->post(route('password.email'), ['email' => 'desconhecido@giracomanda.test']);

        $message = 'Se o e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.';
        $knownResponse->assertSessionHas('success', $message);
        $unknownResponse->assertSessionHas('success', $message);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_guest_can_reset_password_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'usuario@giracomanda.test']);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'nova-senha-segura',
                'password_confirmation' => 'nova-senha-segura',
            ]);

            $response->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('nova-senha-segura', $user->refresh()->password));
    }

    public function test_invalid_reset_token_shows_safe_message(): void
    {
        $user = User::factory()->create(['email' => 'usuario@giracomanda.test']);

        $response = $this->post(route('password.update'), [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Este link é inválido ou expirou. Solicite uma nova recuperação de senha.',
        ]);
    }
}
