<template>
  <div class="page page--narrow">
    <section class="state-panel state-panel--error" aria-labelledby="not-found-title">
      <p class="eyebrow">404 · Page not found</p>
      <h1 id="not-found-title">This page doesn’t exist</h1>
      <p>The address may be incorrect, or the page may have moved.</p>
      <RouterLink class="button button--primary" :to="{ name: 'request-list' }">
        Go to requests
      </RouterLink>
    </section>
  </div>
</template>
