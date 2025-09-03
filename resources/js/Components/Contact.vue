<script setup lang="ts">
import { ref, reactive, watch } from "vue";
import { Button } from "./ui/button";
import { Card, CardHeader, CardContent, CardFooter } from "./ui/card";
import { Label } from "./ui/label";
import { Input } from "./ui/input";
import { useForm, usePage } from '@inertiajs/vue3';
import { Textarea } from "./ui/textarea";
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert";
import { AlertCircle, Building2, Phone, Mail, Clock } from "lucide-vue-next";

interface ContactFormeProps {
  firstName: string;
  lastName: string;
  email: string;
  message: string;
}

interface FlashProps {
  success?: string;
  error?: string;
}


interface PageProps {
  flash: FlashProps;
}

const page = usePage();
const form = useForm({
  firstName: '',
  lastName: '',
  email: '',
  message: ''
});


const showSuccess = ref(false);
const showError = ref(false);
const successMessage = ref('');
const errorMessage = ref('');



watch(() => page.props, (newProps) => {
  const flash = (newProps as any)?.flash;
  
  if (flash?.success) {
    showSuccess.value = true;
    successMessage.value = flash.success;
    setTimeout(() => { showSuccess.value = false; }, 5000);
  }
  
  if (flash?.error) {
    showError.value = true;
    errorMessage.value = flash.error;
    setTimeout(() => { showError.value = false; }, 5000);
  }
}, { immediate: true });

const submit = () => {
  form.post(route('contact.send'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
};
</script>


<template>
  <section class="w-[90%] 2xl:w-[75%] mx-auto py-24 sm:py-32 ">
    <section class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <div>
        <div class="mb-4">
          <h2 class="text-lg text-primary mb-2 tracking-wider">Contact</h2>

          <h2 class="text-3xl md:text-4xl font-bold">Connect With Us</h2>
        </div>
        <p class="mb-8 text-muted-foreground lg:w-5/6">
          Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptatum
          ipsam sint enim exercitationem ex autem corrupti quas tenetur
        </p>

        <div class="flex flex-col gap-4">
          <div>
            <div class="flex gap-2 mb-1">
              <Building2 />
              <div class="font-bold">Find Us</div>
            </div>

            <div>742 Evergreen Terrace, Springfield, IL 62704</div>
          </div>

          <div>
            <div class="flex gap-2 mb-1">
              <Phone />
              <div class="font-bold">Call Us</div>
            </div>

            <div>+1 (619) 123-4567</div>
          </div>

          <div>
            <div class="flex gap-2 mb-1">
              <Mail />
              <div class="font-bold">Mail Us</div>
            </div>

            <div>shadcnpetproject@gmail.com</div>
          </div>

          <div>
            <div class="flex gap-2">
              <Clock />
              <div class="font-bold">Visit Us</div>
            </div>

            <div>
              <div>Monday - Friday</div>
              <div>8AM - 4PM</div>
            </div>
          </div>
        </div>
      </div>

      <!-- form -->
      <Card class="bg-muted/60 dark:bg-card">
        <CardHeader class="text-primary text-2xl"> </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="grid gap-4">

            <!-- Поля формы с ошибками валидации -->
            <div class="flex flex-col md:flex-row gap-8">
              <div class="flex flex-col w-full gap-1.5">
                <Label for="first-name">First Name</Label>
                <Input class="bg-background" id="first-name" type="text" placeholder="Leroy" v-model="form.firstName"
                  :class="{ 'border-red-500': form.errors.firstName }" />
                <div v-if="form.errors.firstName" class="text-red-500 text-sm">
                  {{ form.errors.firstName }}
                </div>
              </div>

              <div class="flex flex-col w-full gap-1.5">
                <Label for="last-name">Last Name</Label>
                <Input class="bg-background" id="last-name" type="text" placeholder="Jenkins" v-model="form.lastName"
                  :class="{ 'border-red-500': form.errors.lastName }" />
                <div v-if="form.errors.lastName" class="text-red-500 text-sm">
                  {{ form.errors.lastName }}
                </div>
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <Label for="email">Email</Label>
              <Input class="bg-background" id="email" type="email" placeholder="ihaveachicken@gmail.com"
                v-model="form.email" :class="{ 'border-red-500': form.errors.email }" />
              <div v-if="form.errors.email" class="text-red-500 text-sm">
                {{ form.errors.email }}
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <Label for="message">Message</Label>
              <Textarea class="bg-background" id="message" placeholder="Your message..." rows="5" v-model="form.message"
                :class="{ 'border-red-500': form.errors.message }" />
              <div v-if="form.errors.message" class="text-red-500 text-sm">
                {{ form.errors.message }}
              </div>
            </div>

            <!-- Success Alert -->
            <Alert v-if="showSuccess" variant="default" class="flex gap-2 bg-transparent items-center">
              <AlertCircle class="w-4 h-4 text-green-600" />
              
              <AlertDescription class="text-green-600">
                {{ successMessage }}
              </AlertDescription>
            </Alert>

            <!-- Error Alert -->
            <Alert v-if="showError" variant="destructive" class="flex gap-2 bg-transparent items-center">
              <AlertCircle class="w-4 h-4" />
              
              <AlertDescription>
                {{ errorMessage }}
              </AlertDescription>
            </Alert>

            <!-- Кнопка с индикатором загрузки -->
            <Button :disabled="form.processing" class="mt-4 flex gap-2 items-center">
              <span v-if="form.processing"
                class="inline-block h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-spin"></span>
              <span>{{ form.processing ? 'Sending...' : 'Send message' }}</span>
            </Button>
          </form>
        </CardContent>
        <CardFooter></CardFooter>
      </Card>
    </section>
  </section>
</template>
