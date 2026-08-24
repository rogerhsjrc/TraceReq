<script setup>
import { formatAmount, formatDate } from '../formatters'

defineProps({
  request: { type: Object, required: true },
})
</script>

<template>
  <article class="request-card">
    <div class="request-card__content">
      <div class="request-card__meta">
        <span>{{ formatDate(request.created_at) }}</span>
        <span class="request-card__amount">
          {{ formatAmount(request.requested_amount, request.currency_code) }}
        </span>
      </div>
      <h2>
        <RouterLink :to="{ name: 'request-detail', params: { id: request.id } }">
          {{ request.title }}
        </RouterLink>
      </h2>
      <p class="request-card__description">{{ request.description }}</p>
    </div>
    <RouterLink
      class="request-card__open"
      :to="{ name: 'request-detail', params: { id: request.id } }"
      :aria-label="`View request: ${request.title}`"
    >
      View details <span aria-hidden="true">→</span>
    </RouterLink>
  </article>
</template>
