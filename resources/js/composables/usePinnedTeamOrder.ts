import { computed, nextTick, ref } from "vue";
import type { Team } from '~/lib/quiz'

export function usePinnedTeamOrder(sortedTeams: () => Team[]) {
  const pinnedTeamIds = ref<string[] | null>(null);
  // Bumped on every freeze so a release from the previous cell does not
  // unpin the order when focus moves straight to the next score input.
  let pinGeneration = 0;

  const visibleTeams = computed(() =>
    orderTeamsByPinnedIds(sortedTeams(), pinnedTeamIds.value),
  );

  function freezeCurrentOrder(): void {
    pinGeneration++;
    if (!pinnedTeamIds.value) {
      pinnedTeamIds.value = visibleTeams.value.map((team) => team.id);
    }
  }

  async function releaseCurrentOrder(): Promise<void> {
    const generation = pinGeneration;
    await nextTick();
    if (generation === pinGeneration) {
      pinnedTeamIds.value = null;
    }
  }

  return {
    visibleTeams,
    freezeCurrentOrder,
    releaseCurrentOrder,
  };
}

export function orderTeamsByPinnedIds(
  teams: Team[],
  pinnedIds: string[] | null,
): Team[] {
  if (!pinnedIds) {
    return teams;
  }

  const teamsById = new Map(teams.map((team) => [team.id, team]));
  const pinnedTeams = pinnedIds
    .map((id) => teamsById.get(id))
    .filter((team): team is Team => Boolean(team));
  const pinnedIdSet = new Set(pinnedTeams.map((team) => team.id));
  const unpinnedTeams = teams.filter((team) => !pinnedIdSet.has(team.id));

  return [...pinnedTeams, ...unpinnedTeams];
}
