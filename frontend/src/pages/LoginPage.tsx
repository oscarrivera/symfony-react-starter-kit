import { FormEvent, useState } from 'react'
import { login } from '../api/client'

type Props = {
  onLoggedIn: () => void
}

export function LoginPage({ onLoggedIn }: Props) {
  const [email, setEmail] = useState('tech@example.test')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    try {
      await login(email, password)
      onLoggedIn()
    } catch (err) {
      const code = err instanceof Error ? err.message : 'login_failed'
      setError(messageFor(code))
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="auth">
      <section className="card">
        <h1>Parte de trabajo</h1>
        <p className="lede">Acceso para técnicos de campo. El token de sesión no se guarda en el navegador.</p>
        <form onSubmit={onSubmit}>
          <label>
            Correo
            <input
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </label>
          <label>
            Contraseña
            <input
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </label>
          {error ? <p className="error" role="alert">{error}</p> : null}
          <button type="submit" disabled={busy}>
            {busy ? 'Entrando…' : 'Entrar'}
          </button>
        </form>
      </section>
    </main>
  )
}

function messageFor(code: string): string {
  switch (code) {
    case 'invalid_credentials':
      return 'Credenciales incorrectas.'
    case 'rate_limited':
      return 'Demasiados intentos. Espere 15 minutos.'
    default:
      return 'No se pudo iniciar sesión.'
  }
}
