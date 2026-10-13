<?php

use App\Models\Roadmap;
use App\Models\RoadmapVote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps retained roadmap records private and returns an accurate gone response', function () {
    $user = User::factory()->create();
    Roadmap::factory()->count(3)->create(['user_id' => $user->id]);
    $this->get(route('roadmap.index'))->assertStatus(410)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->actingAs($user)->get(route('roadmap.index'))->assertStatus(410);
    $this->assertDatabaseCount('roadmaps', 3);
});

it('does not create requests or change votes through retired endpoints', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $roadmap = Roadmap::factory()->create(['votes_count' => 1]);
    RoadmapVote::create(['user_id' => $user->id, 'roadmap_id' => $roadmap->id]);
    $this->actingAs($user)->post(route('roadmap.store'), ['title' => 'New feature', 'description' => 'Request'])->assertStatus(410);
    $this->post(route('roadmap.vote', $roadmap))->assertStatus(410);
    expect($roadmap->fresh()->votes_count)->toBe(1);
    $this->assertDatabaseCount('roadmaps', 1);
    $this->assertDatabaseCount('roadmap_votes', 1);
});

it('preserves authentication boundaries on retired write routes', function () {
    $this->post(route('roadmap.store'))->assertRedirect(route('login'));
    $this->post(route('roadmap.vote', 1))->assertRedirect(route('login'));
});

it('preserves the uniqueness constraint on historical votes', function () {
    $user = User::factory()->create();
    $roadmap = Roadmap::factory()->create();
    RoadmapVote::create(['user_id' => $user->id, 'roadmap_id' => $roadmap->id]);
    $this->expectException(QueryException::class);
    RoadmapVote::create(['user_id' => $user->id, 'roadmap_id' => $roadmap->id]);
});
