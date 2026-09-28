@props(['message'])

<div class="success toast-fallback" role="alert">
    {{ $message }}
</div>

<template data-toast-template>
    <span>{{ $message }}</span>
    <button type="button" class="toast-dismiss" data-toast-dismiss aria-label="Dismiss notification">Dismiss</button>
</template>
