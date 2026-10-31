<script setup>
import { ref } from 'vue';
import TreeSelect from 'primevue/treeselect';

const expandedKeys = ref({});

function collectParentKeys(nodes, keys = {}) {
    for (const node of nodes) {
        if (node.children?.length) {
            keys[node.key] = true;
            collectParentKeys(node.children, keys);
        }
    }
    return keys;
}

// PrimeVue filters the tree but keeps parents collapsed, hiding the matches.
function expandFilterMatches(event) {
    if (event.value.trim()) {
        expandedKeys.value = collectParentKeys(event.filteredNodes);
    }
}
</script>

<template>
    <TreeSelect
        v-model:expandedKeys="expandedKeys"
        selectionMode="single"
        filter
        filterPlaceholder="Suchen..."
        @filter="expandFilterMatches"
    />
</template>
