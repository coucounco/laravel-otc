<?php

namespace coucounco\LaravelOtc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use coucounco\LaravelOtc\Generators\GeneratorContract;
use coucounco\LaravelOtc\Models\OtcToken;
use coucounco\LaravelOtc\Notifications\OneTimeCodeNotification;
use Symfony\Component\HttpFoundation\Response;

class LaravelOtcManager
{
    private $generator;
    private $request;

    public function __construct(
        GeneratorContract $generator
    )
    {
        $this->generator = $generator;
    }

    /**
     * Return true if authenticated using bearer token
     * @return bool
     */
    public function check()
    {
        $token = $this->getRequest()->bearerToken()
            ?? ($this->getRequest()->has('token') ? $this->getRequest()->token : null)
            ?? (session()->has('otc_token') ? session()->get('otc_token') : null);

        if(!isset($token)) return false;

        $otc = $this->findOtcTokenByToken($token);

        return isset($otc)
            && $otc->token === $token
            && $otc->token_valid_until->isAfter(now());
    }

    /**
     * Authenticate using token
     * @param $token
     * @return bool
     */
    public function auth($token): bool {
        $otc = $this->findOtcTokenByToken($token);
        if(!isset($otc) || $otc->token_valid_until->isBefore(now())) {
            return false;
        }
        session()->regenerate(true);
        session()->put('otc_token', $token);
        return true;
    }

    public function logout() {
        session()->forget('otc_token');
        session()->regenerate(true);
    }

    /**
     * Get the authenticated user
     * @return Model|null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function user(): Model|null {
        $token = session()->get('otc_token');
        if(!isset($token)) {
            return null;
        }
        $otc = $this->findOtcTokenByToken($token);
        if(!isset($otc) || $otc->token_valid_until->isBefore(now())) {
            return null;
        }
        return $otc->related;
    }

    public function unauthorizedResponse(Model $related) : Response
    {
        $slug = $this->getModelSlug($related);

        if(!isset($slug)) {
            // todo throw an exceition ?
            // throw new  NoMatchingAuthenticatableException
            return abort(401);
        }

        $identifierColumn = config('otc.authenticatables.' . $slug . '.identifier');

        if ($this->getRequest()->wantsJson()) {
            return response()->json([
                'request_code_url' => route('laravel-otc.request-code'),
                'request_code_body' => [
                    'type' => $slug,
                    'identifier' => $related->$identifierColumn,
                ]
            ], 401);
        }

        return abort(401);
    }

    public function storeCode(Model|string $related, $code) : OtcToken
    {
        if(is_string($related)) {
            $slug = $this->getRequest()->type;
            $modelClass = config('otc.authenticatables.' . $slug . '.model');
            $inputs = [
                'related_type' => $modelClass,
                'identifier' => $related
            ];
        }
        else {
            $inputs = [
                'related_type' => get_class($related),
                'related_id' => $related->id,
            ];
        }


        return OtcToken::create(
            array_merge(
                $inputs,
                [
                    'ip' => $this->getRequest()->ip(),
                    'code' => $code,
                    'code_valid_until' => now()->addMinutes(30),
                ]
            )
        );
    }

    public function getModel() : Model|string|null
    {
        $slug = $this->getRequest()->type;
        $identifier = $this->getRequest()->identifier;

        $modelClass = config('otc.authenticatables.' . $slug . '.model');
        $identifierColumn = config('otc.authenticatables.' . $slug . '.identifier');

        // try to find the model from the database
        if(str_contains($identifierColumn, '.')) {
            [$identifierRelation, $identifierColumn] = explode('.', $identifierColumn);

            $result = call_user_func_array([$modelClass, 'query'], [])
                ->whereHas($identifierRelation, fn($q) => $q->where($identifierColumn, $identifier))
                ->first();
        }
        else {
            $result = call_user_func_array([$modelClass, 'query'], [])->where($identifierColumn, $identifier)->first();
        }

        // if no result found in the database and the register feature is enabled,
        // we allow to send a mail to an unregistered user
        if(!isset($result) && $this->isRegisterable()) {
            $result = $identifier;
        }

        return $result;

    }

    public function checkCode(OtcToken $token = null)
    {
        $token = $token ?? $this->findOtcTokenByRelatedAndCode($this->getModel(), $this->getRequest()->code);

        return isset($token)
            && $token->code == $this->getRequest()->code
            && $token->code_valid_until->isAfter(now());
    }

    public function createCode(Model|string $related)
    {
        $code = $this->generator->generate();
        return $this->storeCode($related, $code);
    }

    public function createToken(OtcToken $token)
    {
        $token->update([
            'code_valid_unit' => now(),

            'token' => Str::random(64),
            'token_valid_until' => now()->addDays(30),
        ]);
    }

    public function sendCode(?Model $related = null, ?OtcToken $token = null)
    {
        $related = $related ?? $this->getModel();

        // if we cant find the related model
        if(!isset($related)) {
            abort(403);
        }

        $token = $token ?? $this->createCode($related);

        $notifierClass = config('otc.notifier_class');
        $notificationClass = config('otc.notification_class');
        if(!isset($notifierClass) || !class_exists($notifierClass)) {
            $notifierClass = Notification::class;
        }
        if(!isset($notificationClass) || !class_exists($notificationClass)) {
            $notificationClass = OneTimeCodeNotification::class;
        }
        if(!is_string($related)) {
            call_user_func_array(
                [$notifierClass, 'sendNow'],
                [$related, new $notificationClass($token)]
            );
        }
        else {
            call_user_func_array(
                [$notifierClass, 'route'],
                ['mail', $related]
            )->notify(new $notificationClass($token));
        }
    }

    private function findOtcTokenByToken(string $token) : ?OtcToken
    {
        return OtcToken::query()
            ->where('token', $token)
            ->latest()
            ->first();
    }

    public function findOtcTokenByRelatedAndCode(Model|string $related, $code) : ?OtcToken
    {
        return OtcToken::query()
            ->when(is_string($related), function($q) use ($related) {
                $slug = $this->getRequest()->type;
                $modelClass = config('otc.authenticatables.' . $slug . '.model');
                $q
                    ->where('related_type', $modelClass)
                    ->where('identifier', $related);
            }, function($q) use ($related) {
                $q
                    ->where('related_id', $related->id)
                    ->where('related_type', get_class($related));
            })
            ->where('ip', $this->getRequest()->ip())
            ->where('code', $code)
            ->latest()
            ->first();
    }

    private function getModelSlug(Model $related) {
        $authenticatables = config('otc.authenticatables');
        foreach($authenticatables as $slug => $a) {
            if($a['model'] === get_class($related)) {
                return $slug;
            }
        }
        return null;
    }

    private function isRegisterable() {
        $slug = $this->getRequest()->type;
        return config('otc.authenticatables.' . $slug . '.register');
    }

    private function register() {
        $slug = $this->getRequest()->type;


    }

    private function getRequest() {
        return $this->request ?? request();
    }

    public function setTestRequest($request) {
        $this->request = $request;
    }
}
