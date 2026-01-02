<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/rag';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'RAG',
        href: index().url,
    }
];

</script>

<template>
    <Head title="RAG" />

    <AppLayout :breadcrumbs="breadcrumbs"> 
        <div class="p-4">
            <div class="space-y-4">
                <Link :href="create().url">
                    <Button variant="default">Upload Document</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
