export type Product = {
  id: number;
  name: string;
  slug: string;
  price_cents: number;
  image_url?: string | null;
  short?: string | null;
  description?: string | null;
  sku?: string | null;
  track_inventory: boolean;
  stock: number | null;
  price_preview?: string | null
  
};