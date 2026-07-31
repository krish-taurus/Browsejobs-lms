<?php

declare(strict_types=1);

use App\Support\WhatsApp\HttpWhatsAppClient;
use Illuminate\Support\Facades\Http;

function fakeWhatsAppOk(): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST123']]]),
    ]);
}

function whatsAppClient(): HttpWhatsAppClient
{
    return new HttpWhatsAppClient([
        'phone_number_id' => '111222333',
        'access_token' => 'token',
        'base_url' => 'https://graph.facebook.com/v21.0',
        'default_country_code' => '91',
        'auth_templates' => ['bj_otp'],
    ]);
}

it('prefixes the default country code onto bare 10-digit numbers', function () {
    fakeWhatsAppOk();

    whatsAppClient()->sendMessage('8114637479', 'Hello!');

    Http::assertSent(fn ($request) => $request['to'] === '918114637479');
});

it('leaves already-international numbers alone', function () {
    fakeWhatsAppOk();

    whatsAppClient()->sendMessage('+91 81146 37479', 'Hello!');

    Http::assertSent(fn ($request) => $request['to'] === '918114637479');
});

it('adds the copy-code button component for authentication templates', function () {
    fakeWhatsAppOk();

    whatsAppClient()->sendMessage('8114637479', 'Your code is 123456.', 'bj_otp', ['123456']);

    Http::assertSent(function ($request) {
        $components = $request['template']['components'];

        return $request['template']['name'] === 'bj_otp'
            && $components[0]['type'] === 'body'
            && $components[0]['parameters'] === [['type' => 'text', 'text' => '123456']]
            && $components[1] === [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => '123456']],
            ];
    });
});

it('sends plain templates without a button component', function () {
    fakeWhatsAppOk();

    whatsAppClient()->sendMessage('8114637479', 'Hi Rahul', 'bj_batch_joined', ['Rahul', 'DE-01', 'Sat']);

    Http::assertSent(fn ($request) => count($request['template']['components']) === 1);
});
