<?php

use App\Enums\DomainVerifyStatus;
use App\Enums\VerificationMethod;
use App\Models\Domain;
use App\Models\DomainVerification;
use App\Models\User;
use App\Services\Verification\DomainVerifier;
use App\Services\Verification\TxtRecordResolver;
use Illuminate\Support\Facades\Http;

function makeDomainWithToken(User $user, string $token = 'tok-abc-123'): Domain
{
    $domain = Domain::factory()->for($user)->create([
        'hostname' => 'example.com',
        'verify_status' => DomainVerifyStatus::Pending,
    ]);

    DomainVerification::factory()->for($domain)->create([
        'method' => VerificationMethod::DnsTxt,
        'token' => $token,
    ]);

    return $domain;
}

/** Bind a fake TXT resolver returning the given records. */
function fakeTxt(array $records): void
{
    app()->bind(TxtRecordResolver::class, fn () => new class($records) implements TxtRecordResolver
    {
        public function __construct(private array $records) {}

        public function resolve(string $hostname): array
        {
            return $this->records;
        }
    });
}

it('verifies via DNS TXT when the record matches', function () {
    $user = User::factory()->create();
    $domain = makeDomainWithToken($user);
    fakeTxt([DomainVerifier::TXT_PREFIX.'tok-abc-123']);

    $this->actingAs($user)
        ->post(route('domains.verify', $domain), ['method' => 'dns_txt'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Domain verified.');

    $domain->refresh();
    expect($domain->verify_status)->toBe(DomainVerifyStatus::Verified)
        ->and($domain->verifications()->first()->verified_at)->not->toBeNull();
});

it('fails DNS TXT when no matching record exists', function () {
    $user = User::factory()->create();
    $domain = makeDomainWithToken($user);
    fakeTxt(['some-other-record']);

    $this->actingAs($user)
        ->post(route('domains.verify', $domain), ['method' => 'dns_txt'])
        ->assertSessionHasErrors('verification');

    $domain->refresh();
    expect($domain->verify_status)->toBe(DomainVerifyStatus::Failed)
        ->and($domain->verifications()->first()->last_error)->not->toBeNull();
});

it('verifies via meta tag', function () {
    $user = User::factory()->create();
    $domain = makeDomainWithToken($user, 'meta-token');
    Http::fake([
        'https://example.com' => Http::response(
            '<html><head><meta name="cmp-site-verification" content="meta-token"></head></html>',
        ),
    ]);

    $this->actingAs($user)
        ->post(route('domains.verify', $domain), ['method' => 'meta_tag'])
        ->assertSessionHas('status', 'Domain verified.');

    expect($domain->refresh()->verify_status)->toBe(DomainVerifyStatus::Verified);
});

it('verifies via well-known file', function () {
    $user = User::factory()->create();
    $domain = makeDomainWithToken($user, 'file-token');
    Http::fake([
        'https://example.com/.well-known/cmp-verification.txt' => Http::response("file-token\n"),
    ]);

    $this->actingAs($user)
        ->post(route('domains.verify', $domain), ['method' => 'file'])
        ->assertSessionHas('status', 'Domain verified.');

    expect($domain->refresh()->verify_status)->toBe(DomainVerifyStatus::Verified);
});

it('rejects an invalid method', function () {
    $user = User::factory()->create();
    $domain = makeDomainWithToken($user);

    $this->actingAs($user)
        ->post(route('domains.verify', $domain), ['method' => 'carrier_pigeon'])
        ->assertSessionHasErrors('method');
});

it('forbids verifying another users domain', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $domain = makeDomainWithToken($owner);

    $this->actingAs($other)
        ->post(route('domains.verify', $domain), ['method' => 'dns_txt'])
        ->assertForbidden();
});
