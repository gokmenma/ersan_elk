<script setup>
import { RouterView } from 'vue-router'
import BottomNav from './components/BottomNav.vue'
import PwaInstallPrompt from './components/PwaInstallPrompt.vue'
import NotificationsDrawer from './components/NotificationsDrawer.vue'
import NavigationProgress from './components/NavigationProgress.vue'
</script>

<template>
  <div class="app-stage">
    <div class="app-shell">
      <main class="app-content" :class="{ 'app-content--bare': $route.meta.chrome === false }">
        <RouterView v-slot="{ Component, route }">
          <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.name" />
          </Transition>
        </RouterView>
      </main>
      <NavigationProgress />
      <PwaInstallPrompt />
      <NotificationsDrawer />
      <BottomNav v-if="$route.meta.chrome !== false" />
    </div>
  </div>
</template>
