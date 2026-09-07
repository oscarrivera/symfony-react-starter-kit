import { describe, expect, it } from 'vitest'
import { getAccessToken, setAccessToken } from './tokenStore'

describe('access token memory store', () => {
  it('keeps the token in process memory and can clear it', () => {
    setAccessToken(null)
    expect(getAccessToken()).toBeNull()

    setAccessToken('header.payload.sig')
    expect(getAccessToken()).toBe('header.payload.sig')

    setAccessToken(null)
    expect(getAccessToken()).toBeNull()
  })
})
