<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({links:Array});
</script>
<template><nav class="pagination" aria-label="Paginação"><template v-for="(link,i) in links" :key="i"><Link v-if="link.url" :href="link.url" :class="{current:link.active}" v-html="link.label.replace('Previous','Anterior').replace('Next','Seguinte')" preserve-scroll/><span v-else v-html="link.label.replace('Previous','Anterior').replace('Next','Seguinte')"/></template></nav></template>
