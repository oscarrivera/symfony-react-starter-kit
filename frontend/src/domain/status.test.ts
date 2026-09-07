import { describe, expect, it } from 'vitest'
import { isWorkOrderStatus, statusLabel, WORK_ORDER_STATUSES } from './status'

describe('work order status helper', () => {
  it('accepts only the documented enum values', () => {
    expect(WORK_ORDER_STATUSES).toEqual(['open', 'in_progress', 'done'])
    expect(isWorkOrderStatus('open')).toBe(true)
    expect(isWorkOrderStatus('in_progress')).toBe(true)
    expect(isWorkOrderStatus('done')).toBe(true)
    expect(isWorkOrderStatus('closed')).toBe(false)
    expect(isWorkOrderStatus('')).toBe(false)
  })

  it('maps statuses to Spanish labels', () => {
    expect(statusLabel('open')).toBe('Abierto')
    expect(statusLabel('in_progress')).toBe('En curso')
    expect(statusLabel('done')).toBe('Hecho')
  })
})
