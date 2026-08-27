<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { ValidationError } from '@/api/http'

import { createRequest } from '../api/requests'
import RequestForm from '../components/RequestForm.vue'

const knownFields = new Set(['title', 'description', 'requested_amount', 'currency_code'])
const router = useRouter()
const submitting = ref(false)
const formError = ref('')
const fieldErrors = reactive({})

function clearFieldErrors() {
  for (const field of Object.keys(fieldErrors)) delete fieldErrors[field]
}

function clearError(field) {
  delete fieldErrors[field]
  formError.value = ''
}

async function submit(input) {
  if (submitting.value) return

  submitting.value = true
  formError.value = ''
  clearFieldErrors()

  try {
    const request = await createRequest(input)
    await router.push({ name: 'request-detail', params: { id: request.id } })
  } catch (error) {
    if (error instanceof ValidationError) {
      for (const [field, messages] of Object.entries(error.errors)) {
        if (knownFields.has(field) && Array.isArray(messages)) fieldErrors[field] = messages
      }

      const hasUnmappedErrors = Object.keys(error.errors).some((field) => !knownFields.has(field))
      if (hasUnmappedErrors || Object.keys(fieldErrors).length === 0) {
        formError.value = error.message
      }
    } else {
      formError.value = 'The request could not be saved. Check your connection and try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="page page--narrow">
    <RouterLink class="back-link" :to="{ name: 'request-list' }">
      <span aria-hidden="true">←</span> Back to requests
    </RouterLink>
    <header class="page-header page-header--stacked">
      <div>
        <p class="eyebrow">New internal request</p>
        <h1>Create request</h1>
        <p class="page-intro">Record the business need and requested amount.</p>
      </div>
    </header>
    <section class="surface" aria-labelledby="request-form-title">
      <div class="surface__header">
        <h2 id="request-form-title">Request information</h2>
        <p>All fields are required.</p>
      </div>
      <RequestForm
        :submitting="submitting"
        :field-errors="fieldErrors"
        :form-error="formError"
        @submit="submit"
        @clear-error="clearError"
      />
    </section>
  </div>
</template>
