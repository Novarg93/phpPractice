<script setup lang="ts">
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/Components/ui/accordion"
import { computed } from "vue"

interface FaqIn {
  id?: number | string
  question: string
  answer: string   // может быть HTML из RichEditor
  value?: string   // опционально
}

const props = defineProps<{
  faqs?: FaqIn[] | null
  title?: string
  subtitle?: string
}>()

// локальный фолбэк (на случай отсутствия пропса)
const fallbackFaqs: FaqIn[] = [
  { question: "Is this template free?", answer: "Yes. It is a free Shadcn/Vue template.", value: "item-1" },
  { question: "Duis aute irure dolor in reprehenderit in voluptate velit?", answer: "Lorem ipsum dolor sit amet...", value: "item-2" },
  { question: "Lorem ipsum dolor sit amet Consectetur natus dolor minus quibusdam?", answer: "Lorem ipsum dolor sit amet consectetur...", value: "item-3" },
  { question: "Excepteur sint occaecat cupidata non proident sunt?", answer: "Lorem ipsum dolor sit amet consectetur, adipisicing elit.", value: "item-4" },
  { question: "Enim ad minim veniam, quis nostrud exercitation ullamco laboris?", answer: "consectetur adipisicing elit. Sint labore.", value: "item-5" },
]

// подготавливаем список к рендеру
const faqItems = computed(() =>
  (props.faqs?.length ? props.faqs : fallbackFaqs).map((f, idx) => ({
    ...f,
    _value: f.value ?? `item-${f.id ?? idx + 1}`,
  })),
)
</script>

<template>
  <section id="faq" class="mx-auto w-[90%] md:w-[700px] py-24 sm:py-32">
    <div class="text-center mb-8">
      <h2 class="text-lg text-primary text-center mb-2 tracking-wider">
        {{ subtitle ?? "FAQS" }}
      </h2>

      <h2 class="text-3xl md:text-4xl text-center font-bold">
        {{ title ?? "Common Questions" }}
      </h2>
    </div>

    <Accordion type="single" collapsible class="AccordionRoot" v-if="faqItems.length">
      <AccordionItem
        v-for="faq in faqItems"
        :key="faq._value"
        :value="faq._value"
        class="px-4 my-4 border border-border rounded-lg bg-card"
      >
        <AccordionTrigger class="text-left font-medium text-base">
          {{ faq.question }}
        </AccordionTrigger>

        <!-- ответ может быть HTML: если источник доверенный (Filament RichEditor), используем v-html -->
        <AccordionContent
          class="text-muted-foreground font-medium text-base"
          v-html="faq.answer"
        />
      </AccordionItem>
    </Accordion>

    <div v-else class="text-center text-muted-foreground">
      We’ll add FAQs soon.
    </div>

    <h3 class="font-medium mt-4">
      Still have questions?
      <a href="#" class="text-muted-foreground underline">Contact us</a>
    </h3>
  </section>
</template>