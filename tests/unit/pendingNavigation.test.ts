import { expect, it, vi } from 'vitest'
import { createPendingNavigation } from '../../resources/js/utils/pendingNavigation'

it('cancels immediately and resumes only the latest visit after all newer edits save', async () => {
  let pending = true
  let finish!: (success: boolean) => void
  const flush = vi.fn(() => new Promise<boolean>(resolve => { finish = resolve }))
  const resume = vi.fn()
  const guard = createPendingNavigation(() => pending, flush, resume)
  const cancel = vi.fn()
  guard('first', cancel)
  guard('latest', cancel)
  expect(cancel).toHaveBeenCalledTimes(2)
  expect(flush).toHaveBeenCalledTimes(1)
  finish(true)
  await Promise.resolve()
  expect(resume).not.toHaveBeenCalled()
  expect(flush).toHaveBeenCalledTimes(2)
  pending = false
  finish(true)
  await Promise.resolve()
  expect(resume).toHaveBeenCalledExactlyOnceWith('latest')
})

it('keeps the current page when saving fails', async () => {
  const resume = vi.fn()
  const guard = createPendingNavigation(() => true, async () => false, resume)
  guard('next', vi.fn())
  await Promise.resolve()
  expect(resume).not.toHaveBeenCalled()
})
