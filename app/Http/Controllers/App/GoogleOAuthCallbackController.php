<?php

namespace App\Http\Controllers\App;

use App\Actions\Google\CompleteGoogleOAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class GoogleOAuthCallbackController extends Controller
{
    public function __invoke(Request $request, CompleteGoogleOAuth $complete): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->redirectWithError('Авторизация Google отменена.');
        }

        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();

        if ($code === '' || $state === '') {
            return $this->redirectWithError('Некорректный ответ Google.');
        }

        try {
            $result = $complete->handle($code, $state);
        } catch (RuntimeException $e) {
            return $this->redirectWithError($e->getMessage());
        } catch (Throwable) {
            return $this->redirectWithError('Не удалось завершить авторизацию Google.');
        }

        $siteId = $result['return_site_id'];

        if ($siteId) {
            return redirect('/app/sites/'.$siteId.'?google=connected');
        }

        return redirect('/app/sites?google=connected');
    }

    private function redirectWithError(string $message): RedirectResponse
    {
        return redirect('/app/sites?google=error&message='.urlencode($message));
    }
}
