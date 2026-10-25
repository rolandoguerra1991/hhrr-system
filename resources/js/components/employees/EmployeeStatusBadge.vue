<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { EmployeeStatus } from '@/types';

defineProps<{
    status: EmployeeStatus;
}>();
</script>

<template>
    <Badge :variant="status === 'active' ? 'secondary' : 'outline'">
        {{ status === 'active' ? 'Active' : 'Terminated' }}
    </Badge>
</template>
