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
import KasaView from './views/KasaView.vue'
import PayrollView from './views/PayrollView.vue'
import AracView from './views/AracView.vue'
import EvrakView from './views/EvrakView.vue'
import GidenEvrakView from './views/GidenEvrakView.vue'
import KmOnaylariView from './views/KmOnaylariView.vue'
import PuantajView from './views/PuantajView.vue'
import KacakView from './views/KacakView.vue'
import NobetOnayView from './views/NobetOnayView.vue'
import IhbarView from './views/IhbarView.vue'
import { auth } from './services/auth.js'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/giris', name: 'login', component: LoginView, meta: { guest: true, chrome: false } },
    { path: '/firma-secimi', name: 'company-select', component: CompanySelectView, meta: { requiresAuth: true, chrome: false } },
    { path: '/', name: 'home', component: HomeView, meta: { requiresAuth: true, requiresCompany: true } },
    { path: '/evrak-takip', name: 'evrak-takip', component: EvrakView, meta: { requiresAuth: true, requiresCompany: true, permission: 'documents' } },
    { path: '/giden-evrak', name: 'giden-evrak', component: GidenEvrakView, meta: { requiresAuth: true, requiresCompany: true, permission: 'documents' } },
    { path: '/giden-evraklar', redirect: '/giden-evrak' },
    { path: '/evrak', redirect: '/evrak-takip' },
    { path: '/evraklar', redirect: '/evrak-takip' },
    { path: '/arac-takip', name: 'arac-takip', component: AracView, meta: { requiresAuth: true, requiresCompany: true, permission: 'vehicles' } },
    { path: '/arac', redirect: '/arac-takip' },
    { path: '/km-onaylari', name: 'km-onaylari', component: KmOnaylariView, meta: { requiresAuth: true, requiresCompany: true, permission: 'vehicles' } },
    { path: '/km-onay', redirect: '/km-onaylari' },
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
    { path: '/kasa', name: 'kasa', component: KasaView, meta: { requiresAuth: true, requiresCompany: true, permission: 'cash' } },
    { path: '/gelir-gider', redirect: '/kasa' },
    { path: '/kasa-yonetimi', redirect: '/kasa' },
    { path: '/bordro', name: 'payroll', component: PayrollView, meta: { requiresAuth: true, requiresCompany: true, permission: 'payroll' } },
    { path: '/maas', redirect: '/bordro' },
    { path: '/puantaj', name: 'puantaj', component: PuantajView, meta: { requiresAuth: true, requiresCompany: true, permission: 'reports' } },
    { path: '/puantaj-takip', redirect: '/puantaj' },
    { path: '/kacak-kontrol', name: 'kacak-kontrol', component: KacakView, meta: { requiresAuth: true, requiresCompany: true, permission: 'illegal' } },
    { path: '/kacak', redirect: '/kacak-kontrol' },
    { path: '/nobet-onay', name: 'nobet-onay', component: NobetOnayView, meta: { requiresAuth: true, requiresCompany: true, permission: 'shifts' } },
    { path: '/nobet', redirect: '/nobet-onay' },
    { path: '/nobetler', redirect: '/nobet-onay' },
    { path: '/ihbar', name: 'ihbar', component: IhbarView, meta: { requiresAuth: true, requiresCompany: true, permission: 'notices' } },
    { path: '/ihbar-yonetimi', redirect: '/ihbar' },
    { path: '/ihbarlar', redirect: '/ihbar' },
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
  if (to.meta.permission && !auth.state.permissions[to.meta.permission] && !(to.name === 'km-onaylari' && auth.state.permissions.km_approvals)) return { name: 'home' }
})

export default router
