import { FormEvent, useEffect, useState } from 'react'
import { createWorkOrder, listWorkOrders, logout, patchWorkOrderStatus } from '../api/client'
import type { WorkOrder } from '../api/types'
import { isWorkOrderStatus, statusLabel, WORK_ORDER_STATUSES } from '../domain/status'

type Props = {
  onLoggedOut: () => void
}

export function WorkOrdersPage({ onLoggedOut }: Props) {
  const [items, setItems] = useState<WorkOrder[]>([])
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function reload() {
    const data = await listWorkOrders()
    setItems(data)
  }

  useEffect(() => {
    void reload().catch(() => setError('No se pudieron cargar los partes.'))
  }, [])

  async function onCreate(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    try {
      await createWorkOrder(title.trim(), description)
      setTitle('')
      setDescription('')
      await reload()
    } catch {
      setError('Revise el título (3–120) y la descripción (máx. 2000).')
    } finally {
      setBusy(false)
    }
  }

  async function onStatus(id: number, status: string) {
    setError(null)
    try {
      await patchWorkOrderStatus(id, status)
      await reload()
    } catch {
      setError('No se pudo actualizar el estado.')
    }
  }

  async function onLogout() {
    await logout()
    onLoggedOut()
  }

  return (
    <div className="app">
      <header className="top">
        <div>
          <p className="kicker">Interno</p>
          <h1>Partes de trabajo</h1>
        </div>
        <button type="button" className="ghost" onClick={() => void onLogout()}>
          Cerrar sesión
        </button>
      </header>

      <section className="card">
        <h2>Nuevo parte</h2>
        <form className="create" onSubmit={onCreate}>
          <label>
            Título
            <input value={title} onChange={(e) => setTitle(e.target.value)} minLength={3} maxLength={120} required />
          </label>
          <label>
            Descripción
            <textarea value={description} onChange={(e) => setDescription(e.target.value)} maxLength={2000} rows={3} />
          </label>
          <button type="submit" disabled={busy}>
            {busy ? 'Creando…' : 'Crear'}
          </button>
        </form>
      </section>

      {error ? <p className="error" role="alert">{error}</p> : null}

      <section className="card">
        <h2>Mis partes</h2>
        {items.length === 0 ? (
          <p className="muted">No hay partes asignados.</p>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Título</th>
                <th>Estado</th>
                <th>Asignado</th>
                <th>Actualizar</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id}>
                  <td>
                    <strong>{item.title}</strong>
                    {item.description ? <div className="muted">{item.description}</div> : null}
                  </td>
                  <td>
                    <span className={`pill pill-${item.status}`}>
                      {isWorkOrderStatus(item.status) ? statusLabel(item.status) : item.status}
                    </span>
                  </td>
                  <td>{item.assignedEmail}</td>
                  <td>
                    <select
                      aria-label={`Estado de ${item.title}`}
                      value={isWorkOrderStatus(item.status) ? item.status : 'open'}
                      onChange={(e) => void onStatus(item.id, e.target.value)}
                    >
                      {WORK_ORDER_STATUSES.map((status) => (
                        <option key={status} value={status}>
                          {statusLabel(status)}
                        </option>
                      ))}
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  )
}
