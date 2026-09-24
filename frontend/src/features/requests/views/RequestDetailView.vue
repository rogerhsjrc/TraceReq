<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import { HttpError } from '@/api/http'

import { getRequest, submitRequest } from '../api/requests'
import RequestStatePanel from '../components/RequestStatePanel.vue'
import { formatAmount, formatDate, formatStatus } from '../formatters'

const route = useRoute()
const request = ref(null)
const loading = ref(true)
const notFound = ref(false)
const error = ref('')
const submitting = ref(false)
const submitError = ref('')
let controller
let submissionVersion = 0

async function loadRequest() {

  invalidateSubmission()

  controller?.abort()
  const activeController = new AbortController()
  controller = activeController
  loading.value = true
  request.value = null
  notFound.value = false
  error.value = ''
  submitError.value = ''
  try {
    request.value = await getRequest(String(route.params.id), { signal: activeController.signal })
  } catch (caught) {
    if (caught instanceof DOMException && caught.name === 'AbortError') return

    if (caught instanceof HttpError && caught.status === 404) {
      notFound.value = true
    } else {
      error.value = 'This request is unavailable right now. Check that the API is running and try again.'
    }
  } finally {
    if (!activeController.signal.aborted) loading.value = false
  }
}

async function handleSubmit(){
  if(submitting.value || request.value?.status !== 'draft') return;

    const requestId = request.value.id
    const version = ++submissionVersion

    const isCurrentSubmission = () =>
      version === submissionVersion &&
      String(route.params.id) === requestId

    submitting.value = true
    submitError.value = ''
  try{
    const updateRequest = await submitRequest(request.value.id)
    if(isCurrentSubmission()){
      request.value = updateRequest
    }else
      console.log("Te cambiaste de solicitud")
  } catch {
    if(isCurrentSubmission())
      submitError.value = 'We couldn’t confirm submission. Please try again.'
  } finally {
    if(isCurrentSubmission())
      submitting.value = false
  }
}

function invalidateSubmission(){
  submissionVersion += 1
  submitting.value = false
  submitError.value = ''
}

watch(() => route.params.id, loadRequest, { immediate: true })
onBeforeUnmount(() => {
  controller?.abort()
  invalidateSubmission()
})
</script>

<template>
  <div class="page page--narrow">
    <RouterLink class="back-link" :to="{ name: 'request-list' }">
      <span aria-hidden="true">←</span> Back to requests
    </RouterLink>

    <RequestStatePanel
      v-if="loading"
      title="Loading request"
      message="Retrieving the saved request details…"
      busy
    />

    <RequestStatePanel
      v-else-if="notFound"
      eyebrow="404 · Not found"
      title="Request not found"
      message="The request may not exist, or the identifier in this address may be invalid."
      tone="error"
    >
      <RouterLink class="button button--primary" :to="{ name: 'request-list' }">
        Return to requests
      </RouterLink>
    </RequestStatePanel>

    <RequestStatePanel
      v-else-if="error"
      eyebrow="Connection issue"
      title="Request couldn’t be loaded"
      :message="error"
      tone="error"
      retryable
      @retry="loadRequest"
    />

    <template v-else-if="request">
      <header class="detail-header">
        <div>
          <p class="eyebrow">Request detail</p>
          <h1>{{ request.title }}</h1>
        </div>
        <div class="request-card__actions">
          <span class="badge" :class="`badge--${request.status}`">
            {{ formatStatus(request.status) }}
          </span>
          <p class="detail-amount">
            <span>Requested amount</span>
            <strong>{{ formatAmount(request.requested_amount, request.currency_code) }}</strong>
          </p>
        </div>
      </header>

      <section class="surface detail-section" aria-labelledby="description-title">
        <h2 id="description-title">Description</h2>
        <p class="detail-description">{{ request.description }}</p>
      </section>

      <section class="surface detail-section" aria-labelledby="record-title">
        <h2 id="record-title">Record information</h2>
        <dl class="detail-grid">
          <div>
            <dt>Request ID</dt>
            <dd class="mono">{{ request.id }}</dd>
          </div>
          <div>
            <dt>Currency code</dt>
            <dd>{{ request.currency_code }}</dd>
          </div>
          <div>
            <dt>Raw amount</dt>
            <dd class="mono">{{ request.requested_amount }}</dd>
          </div>
          <div>
            <dt>Created</dt>
            <dd>{{ formatDate(request.created_at) }}</dd>
          </div>
          <div>
            <dt>Last updated</dt>
            <dd>{{ formatDate(request.updated_at) }}</dd>
          </div>
        </dl>
      </section>
      <section>
        <div v-if="submitError" class="alert alert--error" role="alert">
          <strong>Submission unsuccessful</strong>
          <span>{{ submitError }}</span>
        </div>
        <div class="form-actions">
          <button
            v-if="request.status === 'draft'"
            class="button button--primary"
            type="button"
            :disabled="submitting"
            @click="handleSubmit"
            >
            <span v-if="submitting"
            class="spinner spinner--small" aria-hidden="true"></span>
            {{ submitting ? 'Submitting request...' : 'Submit request' }}
          </button>
        </div>
      </section>
    </template>
  </div>
</template>
