<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { NAlert, NButton, NConfigProvider, NFormItem, NInput, NModal, NSelect, NSpin, NTag, ruRU, dateRuRU } from 'naive-ui'
import { getSlots, createBooking, cancelBooking } from './api'

const slots = ref([])
const timezone = ref('Europe/Moscow')
const selectedDate = ref(null)
const dateMenuOpen = ref(false)
const loading = ref(false)
const busy = ref(false)
const listError = ref('')
const notice = ref('')
const bookingOpen = ref(false)
const cancelOpen = ref(false)
const selectedId = ref(null)
const name = ref('')
const email = ref('')
const fieldErrors = ref({})
const actionError = ref('')
const nameInput = ref(null)
const serverNow = ref(0)
let serverEpoch = 0
let receivedAt = 0
let clock

function dateKey(iso) {
  const parts = new Intl.DateTimeFormat('en', { timeZone: timezone.value, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date(iso))
  const part = (type) => parts.find((item) => item.type === type).value
  return `${part('year')}-${part('month')}-${part('day')}`
}
function time(iso) {
  return new Intl.DateTimeFormat('ru', { timeZone: timezone.value, hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
}
function dateLabel(iso, weekday = 'long') {
  return new Intl.DateTimeFormat('ru', { timeZone: timezone.value, weekday, day: 'numeric', month: 'long' }).format(new Date(iso))
}
function status(slot) {
  return Date.parse(slot.starts_at) <= serverNow.value ? 'past' : slot.status
}
const dateOptions = computed(() => {
  const dates = new Map()
  for (const slot of slots.value) dates.set(dateKey(slot.starts_at), slot.starts_at)
  return [...dates].map(([value, iso]) => ({ value, label: dateLabel(iso) }))
})
const visibleSlots = computed(() => slots.value.filter((slot) => dateKey(slot.starts_at) === selectedDate.value))
const selectedSlot = computed(() => slots.value.find((slot) => slot.id === selectedId.value))
const canBook = computed(() => selectedSlot.value && status(selectedSlot.value) === 'available' && !listError.value)
const dayTitle = computed(() => visibleSlots.value.length ? dateLabel(visibleSlots.value[0].starts_at) : 'Расписание')
const freeCount = computed(() => visibleSlots.value.filter((slot) => status(slot) === 'available').length)
const timezoneLabel = computed(() => timezone.value === 'Europe/Moscow' ? 'московское время' : `Часовой пояс: ${timezone.value}`)
const labels = { available: 'Свободно', booked: 'Занято', past: 'Прошло' }

async function refresh() {
  if (loading.value) return
  loading.value = true
  listError.value = ''
  try {
    const data = await getSlots()
    if (!Array.isArray(data.data) || !data.timezone || !Number.isFinite(Date.parse(data.server_now))) throw new Error('Некорректное расписание')
    timezone.value = data.timezone
    slots.value = data.data
    serverEpoch = Date.parse(data.server_now)
    receivedAt = performance.now()
    serverNow.value = serverEpoch
    if (!dateOptions.value.some((option) => option.value === selectedDate.value)) {
      const firstFuture = slots.value.find((slot) => Date.parse(slot.starts_at) > serverEpoch)
      selectedDate.value = firstFuture ? dateKey(firstFuture.starts_at) : dateOptions.value[0]?.value ?? null
    }
  } catch (error) {
    listError.value = error.message || 'Не удалось загрузить расписание.'
  } finally {
    loading.value = false
  }
}
function openBooking(slot) {
  selectedId.value = slot.id
  name.value = ''
  email.value = ''
  fieldErrors.value = {}
  actionError.value = ''
  notice.value = ''
  bookingOpen.value = true
}
function openCancel(slot) {
  selectedId.value = slot.id
  actionError.value = ''
  notice.value = ''
  cancelOpen.value = true
}
async function submitBooking() {
  if (busy.value || loading.value || !canBook.value) return
  fieldErrors.value = {}
  actionError.value = ''
  if (!name.value.trim()) fieldErrors.value.customer_name = ['Укажите имя.']
  if (!email.value.trim()) fieldErrors.value.customer_email = ['Укажите email.']
  if (Object.keys(fieldErrors.value).length) return
  busy.value = true
  try {
    const result = await createBooking({ slot_id: selectedId.value, customer_name: name.value.trim(), customer_email: email.value.trim() })
    bookingOpen.value = false
    name.value = ''
    email.value = ''
    notice.value = `Бронирование №${result.id} создано.`
    await refresh()
  } catch (error) {
    if (error.status === 422) fieldErrors.value = error.errors
    actionError.value = error.status === 422 ? 'Проверьте выделенные поля.' : error.message
    if (error.status === 409 || error.status === 0 || error.status >= 500) await refresh()
  } finally {
    busy.value = false
  }
}
async function submitCancel() {
  if (busy.value || !selectedSlot.value?.booking_id) return
  busy.value = true
  actionError.value = ''
  try {
    await cancelBooking(selectedSlot.value.booking_id)
    cancelOpen.value = false
    notice.value = 'Бронирование отменено.'
    await refresh()
  } catch (error) {
    actionError.value = error.message
  } finally {
    busy.value = false
  }
}
function clearForm() { name.value = ''; email.value = ''; fieldErrors.value = {} }
onMounted(() => {
  refresh()
  clock = setInterval(() => { if (serverEpoch) serverNow.value = serverEpoch + performance.now() - receivedAt }, 1000)
})
onUnmounted(() => clearInterval(clock))
</script>

<template>
  <NConfigProvider :locale="ruRU" :date-locale="dateRuRU" :theme-overrides="{ common: { primaryColor: '#275a48', primaryColorHover: '#346e59', primaryColorPressed: '#1b4234', borderRadius: '8px', fontFamily: 'system-ui, sans-serif' } }">
    <main>
      <header class="page-header">
        <div><h1>Бронирование переговорной</h1><p>Ежедневно с 09:00 до 18:00 · {{ timezoneLabel }}</p></div>
        <NButton :loading="loading" :disabled="busy" @click="refresh">Обновить</NButton>
      </header>

      <div class="messages" aria-live="polite">
        <NAlert v-if="notice" type="success" closable @close="notice = ''">{{ notice }}</NAlert>
        <NAlert v-if="listError" type="error" title="Расписание не обновлено">
          {{ listError }} <NButton text @click="refresh" :disabled="loading || busy">Повторить загрузку</NButton>
        </NAlert>
      </div>

      <section class="schedule" aria-label="Расписание переговорной" :aria-busy="loading">
        <div class="schedule-toolbar">
          <div class="date-field">
            <label id="date-label">Дата</label>
            <NSelect v-model:value="selectedDate" :options="dateOptions" :disabled="loading || busy || !dateOptions.length" filterable :input-props="{ 'aria-label': 'Дата', role: 'combobox', 'aria-expanded': dateMenuOpen }" @update:show="dateMenuOpen = $event" placeholder="Выберите дату" />
          </div>
          <div class="day-summary"><h2>{{ dayTitle }}</h2><span v-if="visibleSlots.length">Свободно: {{ freeCount }} из {{ visibleSlots.length }}</span></div>
        </div>
        <NSpin :show="loading">
          <div v-if="visibleSlots.length" class="slots">
            <article v-for="slot in visibleSlots" :key="slot.id" class="slot" :class="status(slot)">
              <div class="slot-main">
                <h3>{{ time(slot.starts_at) }}–{{ time(slot.ends_at) }}</h3>
                <NTag :bordered="false" :type="status(slot) === 'available' ? 'success' : 'default'" size="small">{{ labels[status(slot)] }}</NTag>
              </div>
              <NButton v-if="status(slot) === 'available'" type="primary" secondary :disabled="busy || loading || !!listError" :aria-label="`Забронировать ${time(slot.starts_at)}–${time(slot.ends_at)}`" @click="openBooking(slot)">Забронировать</NButton>
              <NButton v-else-if="slot.booking_id" :disabled="busy || loading || !!listError" :aria-label="`Отменить бронь на ${time(slot.starts_at)}`" @click="openCancel(slot)">Отменить бронь</NButton>
              <span v-else class="past-label">Недоступно</span>
            </article>
          </div>
          <p v-else class="empty">{{ loading ? 'Загружаем расписание…' : listError ? 'Повторите загрузку расписания.' : 'На выбранную дату слотов нет.' }}</p>
        </NSpin>
      </section>
      <p class="demo-note">Демонстрационный режим: бронирования общие, любую бронь можно отменить.</p>

      <NModal v-model:show="bookingOpen" preset="card" title="Забронировать время" class="booking-modal" :mask-closable="!busy" :close-on-esc="!busy" :closable="!busy" @after-enter="nameInput?.focus()" @after-leave="clearForm">
        <p v-if="selectedSlot" class="modal-time">{{ dateLabel(selectedSlot.starts_at) }}, {{ time(selectedSlot.starts_at) }}–{{ time(selectedSlot.ends_at) }}<br><small>{{ timezoneLabel }}</small></p>
        <NAlert v-if="actionError" type="error" class="action-error" role="alert">{{ actionError }}</NAlert>
        <NAlert v-if="listError" type="warning" class="action-error">Расписание не обновлено. <NButton text :disabled="loading || busy" @click="refresh">Повторить загрузку</NButton></NAlert>
        <NAlert v-if="!canBook && !loading && !listError" type="warning" class="action-error">Это время уже недоступно. Выберите другой слот.</NAlert>
        <form @submit.prevent="submitBooking">
          <NFormItem label="Имя" :label-props="{ for: 'customer-name' }" :feedback="fieldErrors.customer_name?.[0]" :validation-status="fieldErrors.customer_name ? 'error' : undefined">
            <NInput ref="nameInput" v-model:value="name" :disabled="busy" :input-props="{ id: 'customer-name', required: true, maxlength: 100, autocomplete: 'name' }" placeholder="Как к вам обращаться" />
          </NFormItem>
          <NFormItem label="Email" :label-props="{ for: 'customer-email' }" :feedback="fieldErrors.customer_email?.[0]" :validation-status="fieldErrors.customer_email ? 'error' : undefined">
            <NInput v-model:value="email" :disabled="busy" :input-props="{ id: 'customer-email', type: 'email', required: true, maxlength: 254, autocomplete: 'email' }" placeholder="name@example.com" />
          </NFormItem>
          <p v-if="fieldErrors.slot_id" role="alert">{{ fieldErrors.slot_id[0] }}</p>
          <div class="modal-actions">
            <NButton :disabled="busy" @click="bookingOpen = false">{{ canBook ? 'Назад' : 'Выбрать другое время' }}</NButton>
            <NButton attr-type="submit" type="primary" :loading="busy" :disabled="busy || loading || !canBook">Забронировать</NButton>
          </div>
        </form>
      </NModal>

      <NModal v-model:show="cancelOpen" preset="card" title="Отменить бронирование?" class="booking-modal" :mask-closable="!busy" :close-on-esc="!busy" :closable="!busy">
        <p v-if="selectedSlot" class="modal-time">{{ dateLabel(selectedSlot.starts_at) }}, {{ time(selectedSlot.starts_at) }}–{{ time(selectedSlot.ends_at) }}</p>
        <p>Бронь будет удалена; будущее время снова станет доступным.</p>
        <NAlert v-if="actionError" type="error" class="action-error" role="alert">{{ actionError }}</NAlert>
        <div class="modal-actions">
          <NButton :disabled="busy" @click="cancelOpen = false">Оставить бронь</NButton>
          <NButton type="error" :loading="busy" :disabled="busy" @click="submitCancel">Отменить бронирование</NButton>
        </div>
      </NModal>
    </main>
  </NConfigProvider>
</template>
