<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  message: { type: String, required: true },
  tone: { type: String, default: 'neutral' },
  busy: { type: Boolean, default: false },
  retryable: { type: Boolean, default: false },
})

defineEmits(['retry'])
</script>

<template>
  <section
    class="state-panel"
    :class="`state-panel--${tone}`"
    :aria-busy="busy"
    :role="tone === 'error' ? 'alert' : 'status'"
  >
    <span v-if="busy" class="spinner" aria-hidden="true"></span>
    <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
    <h2>{{ title }}</h2>
    <p>{{ message }}</p>
    <button v-if="retryable" class="button button--secondary" type="button" @click="$emit('retry')">
      Try again
    </button>
    <slot />
  </section>
</template>
