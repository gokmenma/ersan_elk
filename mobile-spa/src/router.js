import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView from './views/HomeView.vue'
import TasksView from './views/TasksView.vue'
import ReportsView from './views/ReportsView.vue'
import ProfileView from './views/ProfileView.vue'
import TaleplerView from './views/TaleplerView.vue'
import LoginView from './views/LoginView.vue'
import CompanySelectView from './views/CompanySelectView.vue'
import PersonnelView from './views/PersonnelView.vue'
import PersonnelFormView from './views/PersonnelFormView.vue'
import PersonnelDetailView from './views/PersonnelDetailView.vue'
import PersonnelSectionView from './views/PersonnelSectionView.vue'
import CariView from './views/CariView.vue'
import CariDetailView from './views/CariDetailView.vue'
import FaturaView from './views/FaturaView.vue'
import { auth } from './services/auth.js'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/giris', name: 'login', component: LoginView, meta: { guest: true, chrome: false } },
    { path: '/firma-secimi', name: 'company-select', component: CompanySelectView, meta: { requiresAuth: true, chrome: false } },
    { path: '/', name: 'home', component: HomeView, meta: { requiresAuth: true, requiresCompany: true } },
    { path: '/gorevler', name: 'tasks', component: TasksView, meta: { requiresAuth: true, requiresCompany: true, permission: 'tasks' } },
    { path: '/raporlar', name: 'reports', component: ReportsView, meta: { requiresAuth: true, requiresCompany: true, permission: 'reports' } },
    { path: '/talepler', name: 'requests', component: TaleplerView, meta: { requiresAuth: true, requiresCompany: true, permission: 'requests' } },
    { path: '/profil', name: 'profile', component: ProfileView, meta: { requiresAuth: true, requiresCompany: true } },
    { path: '/personel', name: 'personnel', component: PersonnelView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel' } },
    { path: '/personel/:id', name: 'personnel-detail', component: PersonnelDetailView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel' } },
    { path: '/personel/:id/bolum/:section', name: 'personnel-section', component: PersonnelSectionView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel' } },
    { path: '/personel/yeni', name: 'personnel-create', component: PersonnelFormView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel', chrome: false } },
    { path: '/personel/:id/duzenle', name: 'personnel-edit', component: PersonnelFormView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel', chrome: false } },
    { path: '/cari-takip', name: 'cari-list', component: CariView, meta: { requiresAuth: true, requiresCompany: true, permission: 'current_accounts' } },
    { path: '/cari-takip/:id', name: 'cari-detail', component: CariDetailView, meta: { requiresAuth: true, requiresCompany: true, permission: 'current_accounts' } },
    { path: '/fatura', name: 'fatura', component: FaturaView, meta: { requiresAuth: true, requiresCompany: true, permission: 'invoices' } },
    { path: '/efatura', redirect: '/fatura' },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  try {
    await auth.bootstrap()
  } catch {
    if (to.name !== 'login') return { name: 'login' }
  }
  if (to.meta.guest && auth.state.authenticated) return auth.state.company ? { name: 'home' } : { name: 'company-select' }
  if (to.meta.requiresAuth && !auth.state.authenticated) return { name: 'login' }
  if (to.meta.requiresCompany && !auth.state.company) return { name: 'company-select' }
  if (to.meta.permission && !auth.state.permissions[to.meta.permission]) return { name: 'home' }
})

export default router
