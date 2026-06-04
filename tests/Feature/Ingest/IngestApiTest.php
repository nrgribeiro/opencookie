<?php

use App\Models\BannerConfig;
use App\Models\ConsentRecord;
use App\Models\Cookie;
use App\Models\Domain;
use App\Models\PolicyVersion;

/** A domain with a published banner, ready for public ingest. */
function liveDomain(): Domain
{
    $domain = Domain::factory()->verified()->create([
        'hostname' => 'example.com',
        'consent_expiry_days' => 365,
    ]);
    BannerConfig::factory()->published()->version(1)->for($domain)->create();
    PolicyVersion::factory()->version(1)->for($domain)->create();

    return $domain;
}

it('returns 404 config for an unknown domain', function () {
    $this->getJson(route('ingest.config', 'dom_missing'))->assertNotFound();
});

it('returns 404 config when no banner is published', function () {
    $domain = Domain::factory()->verified()->create();

    $this->getJson(route('ingest.config', $domain->domain_uid))->assertNotFound();
});

it('returns the public config payload', function () {
    $domain = liveDomain();

    $this->getJson(route('ingest.config', $domain->domain_uid))
        ->assertOk()
        ->assertJsonPath('domainId', $domain->domain_uid)
        ->assertJsonPath('bannerVersion', 1)
        ->assertJsonPath('policyVersion', 1)
        ->assertJsonPath('consentExpiryDays', 365)
        ->assertJsonCount(4, 'categories')
        ->assertJsonPath('categories.0.id', 'necessary')
        ->assertJsonPath('categories.0.required', true);
});

it('stores a consent record and returns 201', function () {
    $domain = liveDomain();

    $this->postJson(route('ingest.consent', $domain->domain_uid), [
        'method' => 'custom',
        'bannerVersion' => 1,
        'policyVersion' => 1,
        'categories' => [
            'necessary' => true,
            'preferences' => true,
            'statistics' => false,
            'marketing' => false,
        ],
        'consentTextHash' => 'sha256:abc',
        'language' => 'en',
    ])
        ->assertCreated()
        ->assertJsonPath('stored', true)
        ->assertJsonStructure(['consentId', 'stored', 'expiresAt']);

    $record = ConsentRecord::first();
    expect($record)->not->toBeNull()
        ->and($record->domain_id)->toBe($domain->id)
        ->and($record->ip_hash)->not->toBeNull()
        ->and($record->expires_at)->not->toBeNull();
});

it('rejects consent with a missing necessary flag', function () {
    $domain = liveDomain();

    $this->postJson(route('ingest.consent', $domain->domain_uid), [
        'method' => 'custom',
        'bannerVersion' => 1,
        'policyVersion' => 1,
        'categories' => ['statistics' => true],
    ])->assertStatus(422);
});

it('rejects consent with an unknown category key', function () {
    $domain = liveDomain();

    $this->postJson(route('ingest.consent', $domain->domain_uid), [
        'method' => 'accept_all',
        'bannerVersion' => 1,
        'policyVersion' => 1,
        'categories' => ['necessary' => true, 'spyware' => true],
    ])->assertStatus(422);
});

it('rejects consent for a domain with no published banner', function () {
    $domain = Domain::factory()->verified()->create();

    $this->postJson(route('ingest.consent', $domain->domain_uid), [
        'method' => 'accept_all',
        'bannerVersion' => 1,
        'policyVersion' => 1,
        'categories' => ['necessary' => true],
    ])->assertNotFound();
});

it('increments the impression aggregate', function () {
    $domain = liveDomain();

    $this->postJson(route('ingest.impression', $domain->domain_uid), [
        'bannerVersion' => 1,
        'language' => 'en',
    ])->assertNoContent();

    $this->postJson(route('ingest.impression', $domain->domain_uid), [
        'bannerVersion' => 1,
        'language' => 'en',
    ])->assertNoContent();

    $row = $domain->bannerImpressions()->first();
    expect($row->count)->toBe(2);
});

it('serves the cookie declaration as javascript', function () {
    $domain = liveDomain();
    Cookie::factory()->for($domain)->create(['name' => '_ga', 'source_domain' => 'google.com']);

    $response = $this->get(route('ingest.declaration', $domain->domain_uid));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/javascript');
    expect($response->getContent())->toContain('_ga')
        ->toContain('cmp-cookie-declaration');
});
