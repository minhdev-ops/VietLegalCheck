<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import UiToast from '@/components/ui/UiToast.vue'

const auth = useAuthStore()
const notifications = useNotificationsStore()

onMounted(() => {
  void auth.load().then(() => {
    if (auth.isAuthenticated) void notifications.load()
  })
})
</script>

<template>
  <RouterView />
  <UiToast />
</template>
