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
        $this->getJson('/api/games')->assertUnauthorized();
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
        $this->getJson('/api/games')->assertOk()->assertJsonCount(1);
        $this->postJson("$url/finish")->assertOk()->assertJsonPath('status', 'finished');
        $this->patchJson("$url/scores/bulk", ['updates' => [
            ['teamId' => $team, 'roundId' => $round, 'score' => 1],
        ]])->assertConflict();
        $this->patchJson($url, ['title' => 'Forbidden'])->assertConflict();
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
        $this->getJson('/api/games')->assertExactJson([]);
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

    private function user(): User
    {
        return User::create(['email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
    }

    private function createGame(): array
    {
        return $this->postJson('/api/games', [
            'title' => 'Quiz', 'teamNames' => ['Alpha', 'Beta'],
            'rounds' => [['title' => 'First', 'maxScore' => 10, 'questionsCount' => 5]],
        ])->assertOk()->assertJsonStructure(['id', 'status', 'statusLabel', 'statusActionLabel', 'teams', 'rounds', 'createdAt', 'updatedAt'])->json();
    }
}
