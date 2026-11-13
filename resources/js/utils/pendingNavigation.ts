export function createPendingNavigation<T>(
  hasPendingChanges: () => boolean,
  flush: () => Promise<boolean>,
  resume: (visit: T) => void
) {
  let latestVisit: T
  let isFlushing = false

  async function saveAndResume(): Promise<void> {
    isFlushing = true

    do {
      if (!await flush()) {
        isFlushing = false
        return
      }
    } while (hasPendingChanges())

    isFlushing = false
    resume(latestVisit)
  }

  return (visit: T, cancel: () => void): void => {
    if (!hasPendingChanges() && !isFlushing) {
      return
    }

    cancel()
    latestVisit = visit

    if (!isFlushing) {
      void saveAndResume()
    }
  }
}
