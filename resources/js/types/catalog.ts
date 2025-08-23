export type Game = {
  id: number;
  name: string;
  slug: string;
};

export type Category = {
  id: number;
  name: string;
  slug: string;
  type: string;
  image?: string | null;
  products_count?: number;
};