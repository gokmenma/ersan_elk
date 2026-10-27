import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView from './views/HomeView.vue'
import { startNavigation, finishNavigation } from './services/navigation.js'
const TasksView = () => import('./views/TasksView.vue')
const ReportsView = () => import('./views/ReportsView.vue')
const ProfileView = () => import('./views/ProfileView.vue')
const TaleplerView = () => import('./views/TaleplerView.vue')
import LoginView from './views/LoginView.vue'
import CompanySelectView from './views/CompanySelectView.vue'
const PersonnelView = () => import('./views/PersonnelView.vue')
const PersonnelFormView = () => import('./views/PersonnelFormView.vue')
const PersonnelDetailView = () => import('./views/PersonnelDetailView.vue')
const PersonnelSectionView = () => import('./views/PersonnelSectionView.vue')
const CariView = () => import('./views/CariView.vue')
const CariDetailView = () => import('./views/CariDetailView.vue')
const FaturaView = () => import('./views/FaturaView.vue')
const KasaView = () => import('./views/KasaView.vue')
const PayrollView = () => import('./views/PayrollView.vue')
const AracView = () => import('./views/AracView.vue')
const EvrakView = () => import('./views/EvrakView.vue')
const GidenEvrakView = () => import('./views/GidenEvrakView.vue')
const KmOnaylariView = () => import('./views/KmOnaylariView.vue')
const PuantajView = () => import('./views/PuantajView.vue')
const KacakView = () => import('./views/KacakView.vue')
const NobetOnayView = () => import('./views/NobetOnayView.vue')
const IhbarView = () => import('./views/IhbarView.vue')
const LogView = () => import('./views/LogView.vue')
const SozlesmeHakedisView = () => import('./views/SozlesmeHakedisView.vue')
const SozlesmeDetailView = () => import('./views/SozlesmeDetailView.vue')
const HakedisDetailView = () => import('./views/HakedisDetailView.vue')
const SupportView = () => import('./views/SupportView.vue')
import { auth } from './services/auth.js'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/giris', name: 'login', component: LoginView, meta: { guest: true, chrome: false } },
    { path: '/firma-secimi', name: 'company-select', component: CompanySelectView, meta: { requiresAuth: true, chrome: false } },
    { path: '/', name: 'home', component: HomeView, meta: { requiresAuth: true, requiresCompany: true } },
    { path: '/evrak-takip', name: 'evrak-takip', component: EvrakView, meta: { requiresAuth: true, requiresCompany: true, permission: 'documents' } },
    { path: '/giden-evrak', name: 'giden-evrak', component: GidenEvrakView, meta: { requiresAuth: true, requiresCompany: true, permission: 'outgoing_documents' } },
    { path: '/giden-evraklar', redirect: '/giden-evrak' },
    { path: '/evrak', redirect: '/evrak-takip' },
    { path: '/evraklar', redirect: '/evrak-takip' },
    { path: '/arac-takip', name: 'arac-takip', component: AracView, meta: { requiresAuth: true, requiresCompany: true, permission: 'vehicles' } },
    { path: '/arac-dashboard', redirect: '/arac-takip' },
    { path: '/arac', redirect: '/arac-takip' },
    { path: '/km-onaylari', name: 'km-onaylari', component: KmOnaylariView, meta: { requiresAuth: true, requiresCompany: true, permission: 'km_approvals' } },
    { path: '/km-onay', redirect: '/km-onaylari' },
    { path: '/gorevler', name: 'tasks', component: TasksView, meta: { requiresAuth: true, requiresCompany: true, permission: 'tasks' } },
    { path: '/raporlar', name: 'reports', component: ReportsView, meta: { requiresAuth: true, requiresCompany: true, permission: 'reports' } },
    { path: '/talepler', name: 'requests', component: TaleplerView, meta: { requiresAuth: true, requiresCompany: true, permission: 'requests' } },
    { path: '/profil', name: 'profile', component: ProfileView, meta: { requiresAuth: true, requiresCompany: true } },
    { path: '/personel', name: 'personnel', component: PersonnelView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel' } },
    { path: '/personel/:id', name: 'personnel-detail', component: PersonnelDetailView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel' } },
    { path: '/personel/:id/bolum/:section', name: 'personnel-section', component: PersonnelSectionView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel' } },
    { path: '/personel/yeni', name: 'personnel-create', component: PersonnelFormView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel_edit', chrome: false } },
    { path: '/personel/:id/duzenle', name: 'personnel-edit', component: PersonnelFormView, meta: { requiresAuth: true, requiresCompany: true, permission: 'personnel_edit', chrome: false } },
    { path: '/cari-takip', name: 'cari-list', component: CariView, meta: { requiresAuth: true, requiresCompany: true, permission: 'current_accounts' } },
    { path: '/cari-takip/:id', name: 'cari-detail', component: CariDetailView, meta: { requiresAuth: true, requiresCompany: true, permission: 'current_accounts' } },
    { path: '/fatura', name: 'fatura', component: FaturaView, meta: { requiresAuth: true, requiresCompany: true, permission: 'invoices' } },
    { path: '/efatura', redirect: '/fatura' },
    { path: '/kasa', name: 'kasa', component: KasaView, meta: { requiresAuth: true, requiresCompany: true, permission: 'cash' } },
    { path: '/gelir-gider', redirect: '/kasa' },
    { path: '/kasa-yonetimi', redirect: '/kasa' },
    { path: '/bordro', name: 'payroll', component: PayrollView, meta: { requiresAuth: true, requiresCompany: true, permission: 'payroll' } },
    { path: '/maas', redirect: '/bordro' },
    { path: '/sozlesme-hakedis', name: 'sozlesme-hakedis', component: SozlesmeHakedisView, meta: { requiresAuth: true, requiresCompany: true, permission: 'contracts' } },
    { path: '/sozlesme-hakedis/:id', name: 'sozlesme-detail', component: SozlesmeDetailView, meta: { requiresAuth: true, requiresCompany: true, permission: 'contracts' } },
    { path: '/sozlesme-hakedis/:id/hakedis/:hakedisId', name: 'hakedis-detail', component: HakedisDetailView, meta: { requiresAuth: true, requiresCompany: true, permission: 'contracts' } },
    { path: '/sozlesme', redirect: '/sozlesme-hakedis' },
    { path: '/sozlesmeler', redirect: '/sozlesme-hakedis' },
    { path: '/hakedis', redirect: '/sozlesme-hakedis' },
    { path: '/hakedisler', redirect: '/sozlesme-hakedis' },
    { path: '/puantaj', name: 'puantaj', component: PuantajView, meta: { requiresAuth: true, requiresCompany: true, permission: 'puantaj' } },
    { path: '/puantaj-takip', redirect: '/puantaj' },
    { path: '/kacak-kontrol', name: 'kacak-kontrol', component: KacakView, meta: { requiresAuth: true, requiresCompany: true, permission: 'illegal' } },
    { path: '/kacak', redirect: '/kacak-kontrol' },
    { path: '/nobet-onay', name: 'nobet-onay', component: NobetOnayView, meta: { requiresAuth: true, requiresCompany: true, permission: 'shifts' } },
    { path: '/nobet', redirect: '/nobet-onay' },
    { path: '/nobetler', redirect: '/nobet-onay' },
    { path: '/ihbar', name: 'ihbar', component: IhbarView, meta: { requiresAuth: true, requiresCompany: true, permission: 'notices' } },
    { path: '/ihbar-yonetimi', redirect: '/ihbar' },
    { path: '/ihbarlar', redirect: '/ihbar' },
    { path: '/loglar', name: 'logs', component: LogView, meta: { requiresAuth: true, requiresCompany: true, permission: 'logs' } },
    { path: '/canli-destek', name: 'live-support', component: SupportView, meta: { requiresAuth: true, requiresCompany: true, permission: 'live_support' } },
    { path: '/log-kayitlari', redirect: '/loglar' },
    { path: '/sistem-loglari', redirect: '/loglar' },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to, from) => {
  if (to.fullPath !== from.fullPath) startNavigation()
  try {
    if (!auth.isFresh()) await auth.bootstrap(true)
  } catch {
    if (to.name !== 'login') return { name: 'login' }
  }
  if (to.meta.guest && auth.state.authenticated) return auth.state.company ? { name: 'home' } : { name: 'company-select' }
  if (to.meta.requiresAuth && !auth.state.authenticated) return { name: 'login' }
  if (to.meta.requiresCompany && !auth.state.company) return { name: 'company-select' }
  if (to.meta.permission && !auth.state.permissions[to.meta.permission]) return { name: 'home' }
  if (to.name === 'personnel-section' && !auth.state.permissions.personnel_edit) return { name: 'personnel-detail', params: { id: to.params.id } }
  if (to.name === 'personnel-section' && to.params.section === 'evraklar' && !auth.state.permissions.personnel_documents) return { name: 'personnel-detail', params: { id: to.params.id } }
})

router.afterEach(() => finishNavigation())

router.onError((error, to) => {
  finishNavigation()
  if (/Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module/i.test(String(error?.message))) {
    window.location.assign(router.resolve(to.fullPath).href)
  }
})

export default router
