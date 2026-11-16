export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

async function request(path, options = {}) {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 12000)
  try {
    const response = await fetch(`/api${path}`, {
      ...options,
      signal: controller.signal,
      headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
    })
    if (response.status === 204) return null
    let data
    try { data = await response.json() } catch { throw new ApiError('Сервер вернул непонятный ответ. Обновите расписание.', response.status) }
    if (!response.ok) throw new ApiError(data.message || 'Запрос не выполнен. Повторите попытку.', response.status, data.errors || {})
    return data
  } catch (error) {
    if (error instanceof ApiError) throw error
    throw new ApiError('Не удалось получить ответ сервера. Обновите расписание и повторите попытку.')
  } finally {
    clearTimeout(timeout)
  }
}

export const getSlots = () => request('/slots')
export const createBooking = (payload) => request('/bookings', { method: 'POST', body: JSON.stringify(payload) })
export const cancelBooking = (id) => request(`/bookings/${id}`, { method: 'DELETE' })
