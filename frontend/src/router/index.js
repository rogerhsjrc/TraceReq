import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: { name: 'request-list' } },
    {
      path: '/requests',
      name: 'request-list',
      component: () => import('@/features/requests/views/RequestListView.vue'),
    },
    {
      path: '/requests/new',
      name: 'request-create',
      component: () => import('@/features/requests/views/RequestCreateView.vue'),
    },
    {
      path: '/requests/:id',
      name: 'request-detail',
      component: () => import('@/features/requests/views/RequestDetailView.vue'),
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

export default router
