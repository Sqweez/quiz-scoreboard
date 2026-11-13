import '@inertiajs/core'

declare module '@inertiajs/core' {
  interface PageProps {
    auth: { user: { id: number; email: string } | null }
  }
}
