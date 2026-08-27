<template>
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <div class="app-shell">
    <header class="app-header">
      <RouterLink class="brand" :to="{ name: 'request-list' }" aria-label="TraceReq requests home">
        <span class="brand__mark" aria-hidden="true">T</span>
        <span>TraceReq</span>
      </RouterLink>
      <nav aria-label="Primary navigation">
        <RouterLink :to="{ name: 'request-list' }">Requests</RouterLink>
      </nav>
      <span class="demo-badge">Local demo</span>
    </header>

    <main id="main-content">
      <RouterView />
    </main>

    <footer class="app-footer">
      <span>TraceReq</span>
      <span>Internal request tracking · Controlled local demo</span>
    </footer>
  </div>
</template>
