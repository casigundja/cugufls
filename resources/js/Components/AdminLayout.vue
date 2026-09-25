<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const open = ref(false);
const groups = computed(() => [...new Set((page.props.navigation || []).map(i => i.group))]);
const name = computed(() => page.props.auth?.user?.name || 'Utilizador');
</script>
<template>
<div class="admin-shell">
<aside class="sidebar" :class="{open}"><a class="brand" href="/"><span class="brand-symbol">g<span>+</span></span><span>família gundja<small>PLATAFORMA DE GESTÃO</small></span></a>
<template v-for="group in groups" :key="group"><small class="nav-heading">{{group}}</small><Link v-for="item in page.props.navigation.filter(i=>i.group===group)" :key="item.href" :href="item.href" class="nav-item" :class="{active:page.url.split('?')[0]===item.href}" @click="open=false"><span class="nav-indicator"></span>{{item.title}}</Link></template></aside>
<div class="admin-main"><header class="admin-header"><div style="display:flex;gap:16px;align-items:center"><button class="mobile-admin-toggle" @click="open=!open" aria-label="Abrir menu">☰</button><span><strong>CUGUFLS</strong> <span style="margin:0 10px;color:#c4ccbd">/</span> Administração</span></div><div class="profile"><a href="/" target="_blank" rel="noopener" style="margin-right:15px">Ver website ↗</a><span class="avatar">{{name.charAt(0)}}</span><span class="profile-name">{{name}}</span><Link href="/logout" method="post" as="button" class="logout">Sair</Link></div></header>
<main class="admin-content"><div v-if="page.props.flash?.success" class="notice" role="status">{{page.props.flash.success}}</div><div v-if="Object.keys(page.props.errors || {}).length" class="error-box" role="alert"><p v-for="(message,key) in page.props.errors" :key="key">{{message}}</p></div><slot/></main></div></div>
</template>
