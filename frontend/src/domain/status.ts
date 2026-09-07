export type WorkOrderStatus = 'open' | 'in_progress' | 'done'

export const WORK_ORDER_STATUSES: readonly WorkOrderStatus[] = ['open', 'in_progress', 'done']

const STATUS_SET = new Set<string>(WORK_ORDER_STATUSES)

export function isWorkOrderStatus(value: string): value is WorkOrderStatus {
  return STATUS_SET.has(value)
}

export function statusLabel(status: WorkOrderStatus): string {
  switch (status) {
    case 'open':
      return 'Abierto'
    case 'in_progress':
      return 'En curso'
    case 'done':
      return 'Hecho'
  }
}
