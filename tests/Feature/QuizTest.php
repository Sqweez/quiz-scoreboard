<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/games/active')->assertUnauthorized();
        $this->getJson('/api/games/finished')->assertUnauthorized();
    }

    public function test_game_lifecycle_preserves_the_client_contract_and_fractional_scores(): void
    {
        $this->actingAs($this->user());
        $game = $this->createGame();
        $this->assertSame('Открытая', $game['statusLabel']);
        $this->assertSame(['Alpha', 'Beta'], array_column($game['teams'], 'name'));
        $team = $game['teams'][0]['id'];
        $round = $game['rounds'][0]['id'];
        $url = "/api/games/{$game['id']}";

        $this->patchJson("$url/scores/bulk", ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => 1.75],
        ]])->assertOk()->assertJsonPath("teams.0.scores.$round", 1.75);
        $this->patchJson("$url/rounds/$round", ['maxScore' => 1.5])
            ->assertOk()->assertJsonPath("teams.0.scores.$round", 1.5);
        $this->patchJson($url, ['title' => 'Updated'])->assertOk()->assertJsonPath('title', 'Updated');
        $this->getJson('/api/games/current')->assertOk()->assertJsonPath('id', $game['id']);
        $this->getJson('/api/games/active')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("$url/finish")->assertOk()->assertJsonPath('status', 'finished');
        $this->patchJson("$url/scores/bulk", ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => 1],
        ]])->assertConflict();
        $this->patchJson($url, ['title' => 'After finish'])->assertOk()->assertJsonPath('title', 'After finish');
        $this->deleteJson($url)->assertOk()->assertExactJson(['ok' => true]);
        $this->getJson($url)->assertNotFound();
    }

    public function test_game_and_nested_entities_are_scoped_to_the_owner_and_game(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        $game = $this->createGame();
        $otherGame = $this->createGame();
        $url = "/api/games/{$game['id']}";
        $round = $game['rounds'][0]['id'];
        $team = $game['teams'][0]['id'];

        $this->patchJson("$url/scores/bulk", ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => 3],
            ['teamId' => $otherGame['teams'][0]['id'], 'roundId' => $round, 'score' => 4],
        ]])->assertNotFound();
        $this->assertEquals(0, DB::table('scores')->where('team_id', $team)->where('round_id', $round)->value('value'));

        $this->actingAs($this->user());
        $this->getJson($url)->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->getJson('/api/games/active')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/games/finished')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_route_bindings_reject_invalid_ids_foreign_children_and_foreign_pages(): void
    {
        $this->actingAs($this->user());
        $game = $this->createGame();
        $otherGame = $this->createGame();
        $url = "/api/games/{$game['id']}";

        $this->getJson('/api/games/not-a-uuid')->assertNotFound();
        $this->patchJson("$url/teams/not-a-uuid", ['name' => 'X'])->assertNotFound();
        $this->patchJson("$url/teams/{$otherGame['teams'][0]['id']}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("$url/rounds/{$otherGame['rounds'][0]['id']}")->assertNotFound();
        $this->get("/games/{$game['id']}")->assertOk();

        $this->actingAs($this->user());
        $this->get("/games/{$game['id']}")->assertNotFound();
        $this->get("/games/{$game['id']}/edit")->assertNotFound();
    }

    public function test_team_and_round_editing_initializes_scores_and_keeps_one_of_each(): void
    {
        $this->actingAs($this->user());
        $game = $this->createGame();
        $url = "/api/games/{$game['id']}";
        $game = $this->postJson("$url/teams", ['name' => 'Gamma'])->assertOk()->json();
        $team = $game['teams'][2]['id'];
        $this->assertCount(1, $game['teams'][2]['scores']);
        $this->patchJson("$url/teams/$team", ['name' => 'Renamed'])->assertOk()->assertJsonPath('teams.2.name', 'Renamed');
        $game = $this->postJson("$url/rounds", ['title' => 'Second'])->assertOk()->json();
        $round = $game['rounds'][1]['id'];
        $this->assertCount(2, $game['teams'][2]['scores']);
        $this->deleteJson("$url/rounds/$round")->assertOk();
        $this->deleteJson("$url/rounds/{$game['rounds'][0]['id']}")->assertUnprocessable();
        $this->deleteJson("$url/teams/$team")->assertOk();
        $this->deleteJson("$url/teams/{$game['teams'][1]['id']}")->assertOk();
        $this->deleteJson("$url/teams/{$game['teams'][0]['id']}")->assertUnprocessable();
        $this->assertDatabaseCount('scores', 1);
    }

    public function test_empty_invalid_and_repeated_score_updates(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/api/games', ['title' => ' '])->assertUnprocessable();
        $game = $this->createGame();
        $url = "/api/games/{$game['id']}/scores/bulk";
        $team = $game['teams'][0]['id'];
        $round = $game['rounds'][0]['id'];
        $this->patchJson($url, ['updates' => []])->assertOk();
        $this->patchJson($url, ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => -4],
            ['teamId' => $team, 'roundId' => $round, 'score' => '12'],
        ]])->assertOk()->assertJsonPath("teams.0.scores.$round", 10);
        $this->patchJson($url, ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => null],
        ]])->assertOk()->assertJsonPath("teams.0.scores.$round", 0);
        $this->patchJson($url, ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => ['bad']],
        ]])->assertUnprocessable();
        $this->patchJson('/api/games/not-a-uuid', ['title' => 'X'])->assertNotFound();
    }

    public function test_home_lists_split_active_and_paginated_finished_summaries(): void
    {
        $this->actingAs($this->user());
        $active = $this->createGame();
        for ($i = 0; $i < 11; $i++) {
            $this->postJson("/api/games/{$this->createGame()['id']}/finish")->assertOk();
        }

        $this->getJson('/api/games/active')->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $active['id'], 'title' => 'Quiz', 'teamsCount' => 2, 'roundsCount' => 1,
                'updatedAt' => $active['updatedAt'], 'finishedAt' => null,
            ]]]);

        $this->getJson('/api/games/finished')->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonStructure(['data' => [['id', 'title', 'teamsCount', 'roundsCount', 'updatedAt', 'finishedAt']]]);
        $this->assertNotNull($this->getJson('/api/games/finished')->json('data.0.finishedAt'));
        $this->getJson('/api/games/finished?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_finished_games_search_by_title_case_insensitively_and_literally(): void
    {
        $this->actingAs($this->user());
        foreach (['Пятничный квиз', 'Субботний КВИЗ', '100% эрудит', 'Draft only'] as $title) {
            $game = $this->createGame();
            $this->patchJson("/api/games/{$game['id']}", ['title' => $title])->assertOk();
            if ($title !== 'Draft only') {
                $this->postJson("/api/games/{$game['id']}/finish")->assertOk();
            }
        }

        $titles = fn (string $search) => array_column($this->getJson('/api/games/finished?search='.urlencode($search))->assertOk()->json('data'), 'title');

        $this->assertEqualsCanonicalizing(['Пятничный квиз', 'Субботний КВИЗ'], $titles('квиз'));
        $this->assertSame(['100% эрудит'], $titles('0%'));
        $this->assertSame([], $titles('%'.'_'));
        $this->assertSame([], $titles('Draft'));
        $this->assertCount(3, $titles('  '));
        $this->getJson('/api/games/finished?search='.str_repeat('a', 256))->assertUnprocessable();
    }

    public function test_stale_games_are_finished_at_their_last_update(): void
    {
        $this->actingAs($this->user());
        $game = $this->createGame();
        $this->patchJson("/api/games/{$game['id']}/scores/bulk", ['updates' => [
            ['teamId' => $game['teams'][0]['id'], 'roundId' => $game['rounds'][0]['id'], 'score' => 3],
        ]])->assertOk();
        $lastUpdate = now()->subHours(3)->startOfSecond();
        DB::table('games')->where('id', $game['id'])->update(['updated_at' => $lastUpdate]);

        $this->artisan('games:finish-stale')->assertSuccessful();

        $this->getJson('/api/games/finished')->assertOk()
            ->assertJsonPath('data.0.id', $game['id'])
            ->assertJsonPath('data.0.finishedAt', $lastUpdate->toISOString());
    }

    private function user(): User
    {
        return User::create(['email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
    }

    private function createGame(): array
    {
        return $this->postJson('/api/games', [
            'title' => 'Quiz', 'teamNames' => ['Alpha', 'Beta'],
            'rounds' => [['title' => 'First', 'maxScore' => 10, 'questionsCount' => 5]],
        ])->assertCreated()->assertJsonStructure(['id', 'status', 'statusLabel', 'statusActionLabel', 'teams', 'rounds', 'createdAt', 'updatedAt'])->json();
    }
}
