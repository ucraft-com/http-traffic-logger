<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger\Tests\Unit;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Uc\HttpTrafficLogger\Tests\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Uc\HttpTrafficLogger\Record;

class RecordTest extends TestCase
{
    public function testCaptureRequest(): void
    {
        $request = Request::create(
            uri: 'https://example.com/foo',
            method: 'GET',
            parameters: ['foo' => 'bar'],
            cookies: ['foo' => 'bar', 'example.com:access-token' => 'baz'],
            server: ['HTTP_FOO' => 'BAR', 'HTTP_BAZ' => 'BAT', 'HTTP_AUTHORIZATION' => 'Token', 'HTTP_COOKIE' => 'example.com:access-token=token;cookie-key=cookie-value'],
            content: 'qux',
        );
        $time = new DateTimeImmutable();
        $record = new Record($time);
        $record->captureRequest($request);

        /** @var array<string, string> $dumped */
        $dumped = $record->dump();

        $this->assertTrue($record->isRequestCaptured());
        $this->assertEquals('https://example.com/foo', $dumped['url']);
        $this->assertEquals('GET', $dumped['method']);
        $this->assertEquals('{"foo":"bar"}', $dumped['query']);
        $this->assertStringContainsStringIgnoringCase('"FOO":["BAR"]', $dumped['req_headers']);
        $this->assertStringNotContainsStringIgnoringCase('AUTHORIZATION', $dumped['req_headers']);
        $this->assertStringContainsStringIgnoringCase('cookie-key=cookie-value', $dumped['req_headers']);
        $this->assertStringNotContainsStringIgnoringCase('example.com:access-token', $dumped['req_headers']);
        $this->assertStringContainsStringIgnoringCase('"foo":"bar"', $dumped['req_cookies']);
        $this->assertStringNotContainsStringIgnoringCase('example.com:access-token', $dumped['req_cookies']);
        $this->assertEquals('qux', $dumped['req_body']);
    }

    public function testCaptureResponse(): void
    {
        $response = new Response('foo', 200, ['Content-Type' => 'text/plain', 'Authorization' => 'Token']);
        $response
            ->withCookie(Cookie::create('cookie-key', 'cookie-value'))
            ->withCookie(Cookie::create('example.com:access-token', 'token'));

        $time = new DateTimeImmutable();
        $record = new Record($time);

        // Here we sleep one second to measure the duration
        sleep(1);
        $record->captureResponse($response);

        /** @var array<string, string> $dumped */
        $dumped = $record->dump();

        $this->assertTrue($record->isResponseCaptured());
        $this->assertEquals('foo', $dumped['res_body']);
        $this->assertEquals(200, $dumped['status']);
        $this->assertStringContainsStringIgnoringCase('Content-Type', $dumped['res_headers']);
        $this->assertStringNotContainsStringIgnoringCase('Authorization', $dumped['res_headers']);
        $this->assertStringContainsStringIgnoringCase('set-cookie', $dumped['res_headers']);
        $this->assertStringContainsStringIgnoringCase('cookie-key=cookie-value', $dumped['res_headers']);
        $this->assertStringNotContainsStringIgnoringCase('example.com:access-token', $dumped['res_headers']);
        $this->assertGreaterThan(1000, $dumped['duration']);
    }
}
