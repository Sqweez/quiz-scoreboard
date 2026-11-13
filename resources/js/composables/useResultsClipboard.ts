import { computed, onBeforeUnmount, ref } from "vue";
import type { Round, Team } from '#shared/quiz'
import { exportResultsToTsv } from "../utils/resultsExport";

export function useResultsClipboard() {
  const copyStatus = ref<"idle" | "success" | "error">("idle");
  let copyStatusTimer: ReturnType<typeof setTimeout> | null = null;

  const copyStatusText = computed(() => {
    if (copyStatus.value === "success") {
      return "Результаты скопированы";
    }

    if (copyStatus.value === "error") {
      return "Не удалось скопировать результаты";
    }

    return "";
  });

  async function copyResults(teams: Team[], rounds: Round[]): Promise<void> {
    try {
      await navigator.clipboard.writeText(exportResultsToTsv(teams, rounds));
      setCopyStatus("success");
    } catch {
      setCopyStatus("error");
    }
  }

  function setCopyStatus(status: "success" | "error"): void {
    copyStatus.value = status;

    if (copyStatusTimer) {
      clearTimeout(copyStatusTimer);
    }

    copyStatusTimer = setTimeout(() => {
      copyStatus.value = "idle";
      copyStatusTimer = null;
    }, 2500);
  }

  onBeforeUnmount(() => {
    if (copyStatusTimer) {
      clearTimeout(copyStatusTimer);
    }
  });

  return {
    copyStatus,
    copyStatusText,
    copyResults,
  };
}
