<script setup lang="ts">
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  // Public — landing, tra cứu văn bản, auth (PublicView.vue / AuthView.vue)
  { path: '/', name: 'home', component: () => import('@/views/public/HomeView.vue') },
  { path: '/van-ban', name: 'legal-search', component: () => import('@/views/public/LegalSearchView.vue') },
  { path: '/van-ban/:id', name: 'legal-document', component: () => import('@/views/public/LegalDocumentView.vue') },
  { path: '/huong-dan', name: 'guide', component: () => import('@/views/public/GuideView.vue') },
  { path: '/dang-nhap', name: 'login', component: () => import('@/views/public/LoginView.vue'), meta: { publicAuth: true } },
  { path: '/dang-ky', name: 'register', component: () => import('@/views/public/RegisterView.vue'), meta: { publicAuth: true } },
  { path: '/quen-mat-khau', name: 'forgot-password', component: () => import('@/views/public/ForgotPasswordView.vue'), meta: { publicAuth: true } },
  { path: '/dat-lai-mat-khau', name: 'reset-password', component: () => import('@/views/public/ResetPasswordView.vue'), meta: { publicAuth: true } },
  { path: '/xac-thuc-email', name: 'verify-email', component: () => import('@/views/public/VerifyEmailView.vue'), meta: { publicAuth: true } },

  // App — không gian làm việc (AppShell.vue)
  {
    path: '/app',
    component: () => import('@/views/app/AppShell.vue'),
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/app/DashboardView.vue') },
      { path: 'du-an', name: 'projects', component: () => import('@/views/app/ProjectsView.vue') },
      { path: 'du-an/tao', name: 'project-new', component: () => import('@/views/app/ProjectNewView.vue') },
      {
        path: 'du-an/:id',
        component: () => import('@/views/app/ProjectShellView.vue'),
        children: [
          { path: '', redirect: { name: 'project-overview' } },
          { path: 'tong-quan', name: 'project-overview', component: () => import('@/views/app/project/OverviewTab.vue') },
          { path: 'khai-bao', name: 'project-wizard', component: () => import('@/views/app/project/WizardTab.vue') },
          { path: 'nghia-vu', name: 'project-obligations', component: () => import('@/views/app/project/ObligationsTab.vue') },
          { path: 'quet', name: 'project-scan', component: () => import('@/views/app/project/ScanTab.vue') },
          { path: 'tai-lieu', name: 'project-documents', component: () => import('@/views/app/project/DocumentsTab.vue') },
          { path: 'thu-tuc', name: 'project-procedures', component: () => import('@/views/app/project/ProceduresTab.vue') },
          { path: 'lich-su', name: 'project-history', component: () => import('@/views/app/project/HistoryTab.vue') },
          { path: 'bao-cao', name: 'project-reports', component: () => import('@/views/app/project/ReportsTab.vue') },
        ],
      },
      { path: 'tro-ly-ai', name: 'assistant', component: () => import('@/views/app/AssistantView.vue') },
      { path: 'bao-cao', name: 'reports', component: () => import('@/views/app/ReportsView.vue') },
      { path: 'thong-bao', name: 'notifications', component: () => import('@/views/app/NotificationsView.vue') },
      { path: 'to-chuc', name: 'organization', component: () => import('@/views/app/OrganizationView.vue') },
      { path: 'tai-khoan', name: 'settings', component: () => import('@/views/app/SettingsView.vue') },
    ],
  },

  // Admin — khu vực quản trị (AdminShell.vue)
  {
    path: '/admin',
    component: () => import('@/views/admin/AdminShell.vue'),
    children: [
      { path: '', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue') },
      { path: 'van-ban', name: 'admin-documents', component: () => import('@/views/admin/DocumentsView.vue') },
      { path: 'van-ban/:id', name: 'admin-document', component: () => import('@/views/admin/DocumentEditView.vue') },
      { path: 'quy-tac', name: 'admin-rules', component: () => import('@/views/admin/RulesView.vue') },
      { path: 'quy-tac/:id', name: 'admin-rule', component: () => import('@/views/admin/RuleEditView.vue') },
      { path: 'thu-tuc', name: 'admin-procedures', component: () => import('@/views/admin/ProceduresView.vue') },
      { path: 'chuyen-gia', name: 'admin-expert-reviews', component: () => import('@/views/admin/ExpertReviewsView.vue') },
      { path: 'nhat-ky', name: 'admin-audit', component: () => import('@/views/admin/AuditLogView.vue') },
      { path: 'cau-hinh', name: 'admin-settings', component: () => import('@/views/admin/SettingsView.vue') },
      { path: 'nguoi-dung', name: 'admin-users', component: () => import('@/views/admin/UsersView.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/public/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(_to, _from, saved) {
    return saved ?? { top: 0 }
  },
})

const PUBLIC_NAMES: Record<string, true> = {
  home: true,
  'legal-search': true,
  'legal-document': true,
  guide: true,
  login: true,
  register: true,
  'forgot-password': true,
  'reset-password': true,
  'verify-email': true,
  'not-found': true,
}

router.beforeEach(async (to) => {
  if (PUBLIC_NAMES[to.name as string]) return true

  const auth = useAuthStore()
  if (!auth.user) await auth.load()
  if (!auth.isAuthenticated) {
    return { name: 'login', query: { next: to.fullPath } }
  }
  if (to.path.startsWith('/admin') && !auth.isAdmin && !auth.isExpert) {
    return { name: 'dashboard' }
  }
  return true
})

export { router }
