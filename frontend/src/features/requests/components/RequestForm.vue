<script setup>
import { reactive } from 'vue'

const props = defineProps({
  submitting: { type: Boolean, default: false },
  fieldErrors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'clear-error'])

const form = reactive({
  title: '',
  description: '',
  requested_amount: '',
  currency_code: 'USD',
})

function errorId(field) {
  return props.fieldErrors[field] ? `${field}-error` : undefined
}

function submit() {
  if (props.submitting) return
  emit('submit', { ...form })
}
</script>

<template>
  <form class="request-form" novalidate @submit.prevent="submit">
    <div v-if="formError" class="alert alert--error" role="alert">
      <strong>We couldn’t create the request.</strong>
      <span>{{ formError }}</span>
    </div>

    <div class="form-field">
      <label for="title">Title</label>
      <input
        id="title"
        v-model="form.title"
        name="title"
        type="text"
        maxlength="180"
        autocomplete="off"
        required
        :aria-invalid="Boolean(fieldErrors.title)"
        :aria-describedby="errorId('title')"
        @input="$emit('clear-error', 'title')"
      />
      <div class="field-meta">
        <p v-if="fieldErrors.title" id="title-error" class="field-error">
          {{ fieldErrors.title[0] }}
        </p>
        <span>{{ form.title.length }}/180</span>
      </div>
    </div>

    <div class="form-field">
      <label for="description">Description</label>
      <textarea
        id="description"
        v-model="form.description"
        name="description"
        rows="7"
        maxlength="5000"
        required
        :aria-invalid="Boolean(fieldErrors.description)"
        :aria-describedby="errorId('description')"
        @input="$emit('clear-error', 'description')"
      ></textarea>
      <div class="field-meta">
        <p v-if="fieldErrors.description" id="description-error" class="field-error">
          {{ fieldErrors.description[0] }}
        </p>
        <span>{{ form.description.length }}/5000</span>
      </div>
    </div>

    <div class="form-grid">
      <div class="form-field">
        <label for="requested_amount">Requested amount</label>
        <input
          id="requested_amount"
          v-model="form.requested_amount"
          name="requested_amount"
          type="text"
          inputmode="decimal"
          autocomplete="off"
          placeholder="2500.00"
          pattern="\d{1,15}(\.\d{1,4})?"
          required
          :aria-invalid="Boolean(fieldErrors.requested_amount)"
          :aria-describedby="errorId('requested_amount') || 'amount-help'"
          @input="$emit('clear-error', 'requested_amount')"
        />
        <p v-if="fieldErrors.requested_amount" id="requested_amount-error" class="field-error">
          {{ fieldErrors.requested_amount[0] }}
        </p>
        <p v-else id="amount-help" class="field-help">Up to four decimal places.</p>
      </div>

      <div class="form-field">
        <label for="currency_code">Currency</label>
        <input
          id="currency_code"
          v-model="form.currency_code"
          name="currency_code"
          type="text"
          list="currency-options"
          maxlength="3"
          autocomplete="off"
          pattern="[A-Za-z]{3}"
          required
          :aria-invalid="Boolean(fieldErrors.currency_code)"
          :aria-describedby="errorId('currency_code') || 'currency-help'"
          @input="$emit('clear-error', 'currency_code')"
        />
        <datalist id="currency-options">
          <option value="ARS">Argentine peso</option>
          <option value="USD">US dollar</option>
          <option value="EUR">Euro</option>
          <option value="BRL">Brazilian real</option>
        </datalist>
        <p v-if="fieldErrors.currency_code" id="currency_code-error" class="field-error">
          {{ fieldErrors.currency_code[0] }}
        </p>
        <p v-else id="currency-help" class="field-help">Three-letter ISO code.</p>
      </div>
    </div>

    <div class="form-actions">
      <RouterLink class="button button--ghost" :to="{ name: 'request-list' }">Cancel</RouterLink>
      <button class="button button--primary" type="submit" :disabled="submitting">
        <span v-if="submitting" class="spinner spinner--small" aria-hidden="true"></span>
        {{ submitting ? 'Creating draft…' : 'Save as draft' }}
      </button>
    </div>
  </form>
</template>
