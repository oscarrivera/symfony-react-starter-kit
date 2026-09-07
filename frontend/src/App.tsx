import { StrictMode, useEffect, useState } from 'react'
import { restoreSession } from './api/client'
import { LoginPage } from './pages/LoginPage'
import { WorkOrdersPage } from './pages/WorkOrdersPage'

type Session = 'booting' | 'anon' | 'auth'

export function App() {
  const [session, setSession] = useState<Session>('booting')

  useEffect(() => {
    void restoreSession().then((ok) => setSession(ok ? 'auth' : 'anon'))
  }, [])

  if (session === 'booting') {
    return (
      <div className="boot">
        <p>Cargando sesión…</p>
      </div>
    )
  }

  if (session === 'anon') {
    return <LoginPage onLoggedIn={() => setSession('auth')} />
  }

  return <WorkOrdersPage onLoggedOut={() => setSession('anon')} />
}

export function Root() {
  return (
    <StrictMode>
      <App />
    </StrictMode>
  )
}
