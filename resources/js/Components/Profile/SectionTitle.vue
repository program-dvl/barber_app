<template>
    <div class="flex justify-between md:col-span-1">
        <div class="px-4 sm:px-0">
            <h2 class="cd-section-title">
                <slot name="title" />
            </h2>

            <p class="mt-2 text-sm leading-relaxed text-[var(--text-muted)]">
                <slot name="description" />
            </p>
        </div>

        <div class="px-4 sm:px-0">
            <slot name="aside" />
        </div>
    </div>
</template>
