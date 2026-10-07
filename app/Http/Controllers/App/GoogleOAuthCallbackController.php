<?php

namespace App\Http\Controllers\App;

use App\Actions\Auth\CompleteGoogleLogin;
use App\Actions\Auth\IssueGoogleLoginCode;
use App\Actions\Google\CompleteGoogleOAuth;
use App\Http\Controllers\Controller;
use App\Support\GoogleOAuthState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class GoogleOAuthCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        CompleteGoogleOAuth $completeConnection,
        CompleteGoogleLogin $completeLogin,
        IssueGoogleLoginCode $issueGoogleLoginCode,
    ): RedirectResponse {
        if ($request->filled('error')) {
            return $this->redirectForFailedIntent(
                $this->resolveIntentFromState($request->string('state')->toString()),
                'Авторизация Google отменена.',
            );
        }

        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();

        if ($code === '' || $state === '') {
            return $this->redirectForFailedIntent(null, 'Некорректный ответ Google.');
        }

        $intent = $this->resolveIntentFromState($state);

        if ($intent === 'login') {
            return $this->handleLoginCallback($code, $state, $completeLogin, $issueGoogleLoginCode);
        }

        return $this->handleConnectionCallback($code, $state, $completeConnection);
    }

    private function handleLoginCallback(
        string $code,
        string $state,
        CompleteGoogleLogin $completeLogin,
        IssueGoogleLoginCode $issueGoogleLoginCode,
    ): RedirectResponse {
        try {
            $result = $completeLogin->handle($code, $state);
            $loginCode = $issueGoogleLoginCode->handle($result['user']);
        } catch (RuntimeException $e) {
            return $this->redirectLoginError($e->getMessage());
        } catch (Throwable) {
            return $this->redirectLoginError(__('auth.google_failed'));
        }

        return redirect('/app/login?google_code='.urlencode($loginCode));
    }

    private function handleConnectionCallback(
        string $code,
        string $state,
        CompleteGoogleOAuth $complete,
    ): RedirectResponse {
        try {
            $result = $complete->handle($code, $state);
        } catch (RuntimeException $e) {
            return $this->redirectConnectionError($e->getMessage());
        } catch (Throwable) {
            return $this->redirectConnectionError('Не удалось завершить авторизацию Google.');
        }

        $siteId = $result['return_site_id'];

        if ($siteId) {
            return redirect('/app/sites/'.$siteId.'?google=connected');
        }

        return redirect('/app/sites?google=connected');
    }

    private function resolveIntentFromState(string $state): ?string
    {
        if ($state === '') {
            return null;
        }

        try {
            $payload = GoogleOAuthState::decrypt($state);

            return isset($payload['intent']) && is_string($payload['intent'])
                ? $payload['intent']
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function redirectForFailedIntent(?string $intent, string $message): RedirectResponse
    {
        if ($intent === 'login') {
            return $this->redirectLoginError($message);
        }

        return $this->redirectConnectionError($message);
    }

    private function redirectLoginError(string $message): RedirectResponse
    {
        return redirect('/app/login?google=error&message='.urlencode($message));
    }

    private function redirectConnectionError(string $message): RedirectResponse
    {
        return redirect('/app/sites?google=error&message='.urlencode($message));
    }
}
