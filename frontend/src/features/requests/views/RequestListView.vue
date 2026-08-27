<script setup>
import { onMounted, ref } from 'vue'

import { listRequests } from '../api/requests'
import RequestCard from '../components/RequestCard.vue'
import RequestStatePanel from '../components/RequestStatePanel.vue'

const requests = ref([])
const loading = ref(true)
const error = ref('')

async function loadRequests() {
  loading.value = true
  error.value = ''

  try {
    requests.value = await listRequests()
  } catch {
    error.value = 'The request list is unavailable right now. Check that the API is running and try again.'
  } finally {
    loading.value = false
  }
}

onMounted(loadRequests)
</script>

<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="eyebrow">Internal spending</p>
        <h1>Requests</h1>
        <p class="page-intro">Review the latest internal requests or capture a new one.</p>
      </div>
      <RouterLink class="button button--primary" :to="{ name: 'request-create' }">
        <span aria-hidden="true">＋</span> New request
      </RouterLink>
    </header>

    <RequestStatePanel
      v-if="loading"
      title="Loading requests"
      message="Retrieving the latest saved requests…"
      busy
    />

    <RequestStatePanel
      v-else-if="error"
      eyebrow="Connection issue"
      title="Requests couldn’t be loaded"
      :message="error"
      tone="error"
      retryable
      @retry="loadRequests"
    />

    <RequestStatePanel
      v-else-if="requests.length === 0"
      eyebrow="Ready when you are"
      title="No requests yet"
      message="Create the first internal spending request to begin the record."
    >
      <RouterLink class="button button--primary" :to="{ name: 'request-create' }">
        Create the first request
      </RouterLink>
    </RequestStatePanel>

    <section v-else aria-label="Saved requests">
      <div class="section-heading">
        <p>{{ requests.length }} {{ requests.length === 1 ? 'request' : 'requests' }}</p>
        <p>Newest first</p>
      </div>
      <div class="request-list">
        <RequestCard v-for="request in requests" :key="request.id" :request="request" />
      </div>
    </section>
  </div>
</template>
