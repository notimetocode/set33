<?php

namespace App\Http\Controllers\App;

use App\Actions\Github\CompleteGithubOAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class GithubOAuthCallbackController extends Controller
{
    public function __invoke(Request $request, CompleteGithubOAuth $complete): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->redirectWithError('Авторизация GitHub отменена.');
        }

        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();

        if ($code === '' || $state === '') {
            return $this->redirectWithError('Некорректный ответ GitHub.');
        }

        try {
            $result = $complete->handle($code, $state);
        } catch (RuntimeException $e) {
            return $this->redirectWithError($e->getMessage());
        } catch (Throwable) {
            return $this->redirectWithError('Не удалось завершить авторизацию GitHub.');
        }

        $siteId = $result['return_site_id'];

        if ($siteId) {
            return redirect('/app/sites/'.$siteId.'?github=connected');
        }

        return redirect('/app/sites?github=connected');
    }

    private function redirectWithError(string $message): RedirectResponse
    {
        return redirect('/app/sites?github=error&message='.urlencode($message));
    }
}
