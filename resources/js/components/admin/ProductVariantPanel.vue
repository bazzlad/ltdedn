<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface AxisValueInput {
    id: number | null;
    value: string;
    sort_order: number;
}

interface AxisInput {
    id: number | null;
    name: string;
    sort_order: number;
    values: AxisValueInput[];
}

interface AxisValueProp {
    id: number;
    value: string;
    sort_order: number;
}

interface AxisProp {
    id: number;
    name: string;
    sort_order: number;
    values: AxisValueProp[];
}

const props = defineProps<{
    productId: number;
    initialAxes: AxisProp[];
    defaultPrice: number | string | null;
    currency: string;
}>();

const axes = reactive<AxisInput[]>(
    props.initialAxes.map((a) => ({
        id: a.id,
        name: a.name,
        sort_order: a.sort_order,
        values: a.values.map((v) => ({ id: v.id, value: v.value, sort_order: v.sort_order })),
    })),
);

const savingAxes = ref(false);
const regenerating = ref(false);
const regenPrice = ref<string>(props.defaultPrice != null ? String(props.defaultPrice) : '');
const regenStock = ref<string>('0');

function addAxis(): void {
    axes.push({ id: null, name: '', sort_order: axes.length, values: [{ id: null, value: '', sort_order: 0 }] });
}

function removeAxis(idx: number): void {
    axes.splice(idx, 1);
}

function addValue(axisIdx: number): void {
    axes[axisIdx].values.push({ id: null, value: '', sort_order: axes[axisIdx].values.length });
}

function removeValue(axisIdx: number, valueIdx: number): void {
    axes[axisIdx].values.splice(valueIdx, 1);
}

function saveAxes(): void {
    if (savingAxes.value) return;
    savingAxes.value = true;

    const payload = {
        axes: axes.map((a, i) => ({
            id: a.id,
            name: a.name,
            sort_order: i,
            values: a.values.map((v, j) => ({
                id: v.id,
                value: v.value,
                sort_order: j,
            })),
        })),
    };

    router.post(`/admin/products/${props.productId}/variants/axes`, payload, {
        preserveScroll: true,
        onFinish: () => {
            savingAxes.value = false;
        },
    });
}

function regenerate(): void {
    if (regenerating.value) return;
    const priceMinor = regenPrice.value.trim() === '' ? 0 : Math.round(parseFloat(regenPrice.value) * 100);
    regenerating.value = true;
    router.post(
        `/admin/products/${props.productId}/variants/regenerate`,
        {
            default_price_amount: priceMinor,
            currency: props.currency || 'gbp',
            default_stock_on_hand: parseInt(regenStock.value || '0', 10),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                regenerating.value = false;
            },
        },
    );
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Variants</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <p class="text-sm text-gray-600">
                Define axes (e.g. Size, Colour) and their values. After saving axes, click "Regenerate SKUs" to create one SKU per combination. Existing SKUs with orders or editions are deactivated rather than deleted if they no longer fit.
            </p>

            <div v-for="(axis, axisIdx) in axes" :key="axisIdx" class="rounded border p-3">
                <div class="flex items-center gap-2">
                    <input
                        v-model="axis.name"
                        type="text"
                        placeholder="Axis name (e.g. Size)"
                        class="flex-1 rounded border px-2 py-1 text-sm"
                    />
                    <button type="button" class="text-xs text-red-600" @click="removeAxis(axisIdx)">Remove axis</button>
                </div>
                <div class="mt-2 space-y-1">
                    <div v-for="(value, valueIdx) in axis.values" :key="valueIdx" class="flex items-center gap-2">
                        <input
                            v-model="value.value"
                            type="text"
                            placeholder="Value (e.g. S)"
                            class="flex-1 rounded border px-2 py-1 text-sm"
                        />
                        <button
                            type="button"
                            class="text-xs text-gray-500"
                            :disabled="axis.values.length <= 1"
                            @click="removeValue(axisIdx, valueIdx)"
                        >
                            Remove
                        </button>
                    </div>
                    <button type="button" class="text-xs text-blue-600" @click="addValue(axisIdx)">+ Add value</button>
                </div>
            </div>

            <button type="button" class="rounded border px-3 py-1 text-sm" @click="addAxis">+ Add axis</button>

            <div class="flex items-center gap-2 border-t pt-3">
                <button
                    type="button"
                    :disabled="savingAxes"
                    class="rounded bg-black px-3 py-1 text-sm text-white disabled:opacity-40"
                    @click="saveAxes"
                >
                    {{ savingAxes ? 'Saving…' : 'Save axes' }}
                </button>
            </div>

            <div class="space-y-2 border-t pt-4">
                <h3 class="text-sm font-medium">Regenerate SKUs</h3>
                <p class="text-xs text-gray-600">
                    Creates one SKU per combination of axis values using this default price and stock. Existing SKUs matching a combination are left unchanged.
                </p>
                <div class="flex items-center gap-2">
                    <label class="text-xs">
                        Default price ({{ props.currency.toUpperCase() }})
                        <input v-model="regenPrice" type="number" step="0.01" min="0" class="mt-1 block rounded border px-2 py-1 text-sm" />
                    </label>
                    <label class="text-xs">
                        Default stock
                        <input v-model="regenStock" type="number" min="0" class="mt-1 block rounded border px-2 py-1 text-sm" />
                    </label>
                    <button
                        type="button"
                        :disabled="regenerating"
                        class="rounded border border-black px-3 py-1 text-sm text-black disabled:opacity-40"
                        @click="regenerate"
                    >
                        {{ regenerating ? 'Working…' : 'Regenerate SKUs' }}
                    </button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
