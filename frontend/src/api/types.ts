export type WorkOrder = {
  id: number
  title: string
  description: string
  status: string
  assignedEmail: string
  createdAt: string
  updatedAt: string
}

export type LoginResult = {
  token: string
  expiresAt: string
}
