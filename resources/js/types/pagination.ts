export type Paginator<T> = {
  data: T[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
};