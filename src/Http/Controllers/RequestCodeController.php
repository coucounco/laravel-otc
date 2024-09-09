<?php

namespace coucounco\LaravelOtc\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use coucounco\LaravelOtc\Generators\GeneratorContract;
use coucounco\LaravelOtc\LaravelOtcManager;
use coucounco\LaravelOtc\Notifications\OneTimeCodeNotification;
use function PHPUnit\Framework\callback;

class RequestCodeController extends Controller
{
    private $manager;

    public function __construct(
        LaravelOtcManager $manager,
    )
    {
        $this->manager = $manager;
    }

    public function __invoke() {

        request()->validate([
            'type'  => 'required|string',
            'identifier'    => 'required|string',
            'redirect_url'      => 'nullable|string',
        ]);

        // send code
        $this->manager->sendCode();

        if(request()->wantsJson()) {
            return response()->json([
                'success' => true
            ]);
        }
        return request()->has('redirect_url')
            ? redirect()->to(request()->redirect_url)
            : response('', 200);
    }
}
