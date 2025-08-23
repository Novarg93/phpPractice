<script setup lang="ts">
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb'


type Game = { id: number; name: string; slug: string }
type Category = { id: number; name: string; slug: string; type: string }
type Product = {
  id: number; name: string; slug: string; price_cents: number;
  image?: string | null; short?: string | null; description?: string | null;
  sku?: string | null; track_inventory: boolean; stock: number | null;
}


const props = defineProps<{
  game: Game
  category?: Category | null
  product?: Product | null
}>()

const { game, category, product } = props
</script>

<template>
  <Breadcrumb>
    <BreadcrumbList>
      <BreadcrumbItem>
        <BreadcrumbLink href="/">Games</BreadcrumbLink>
      </BreadcrumbItem>

      <BreadcrumbSeparator />

      <BreadcrumbItem>
        <BreadcrumbLink :href="route('games.show', game.slug)" class="hover:underline">
          {{ game.name }}
        </BreadcrumbLink>
      </BreadcrumbItem>

      <template v-if="category">
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink :href="route('categories.show', [game.slug, category.slug])" class="hover:underline">
            {{ category.name }}
          </BreadcrumbLink>
        </BreadcrumbItem>
      </template>

      <template v-if="product">
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ product.name }}</BreadcrumbPage>
        </BreadcrumbItem>
      </template>
    </BreadcrumbList>
  </Breadcrumb>
</template>