import type { Product } from '@/types'

export type ProductWithGroups = Product & {
  option_groups?: Array<SelectorGroup | QtyGroup | DoubleRangeGroup>
}

export type Currency = 'USD' | 'EUR' | 'GBP' | string

export interface OptionItem {
  id: number
  title: string
  delta_cents?: number | null
  delta_percent?: number | null
  is_default?: boolean
  is_active?: boolean
  allow_class_value_ids?: number[] | null   
  allow_slot_value_ids?: number[] | null 
}

interface BaseGroup {
  id: number
  title: string
  is_required?: boolean
}

export interface SelectorGroup extends BaseGroup {
  type: 'selector'
   code?: string | null
  selection_mode: 'single' | 'multi'
  pricing_mode: 'absolute' | 'percent'
  multiply_by_qty?: boolean
  values: OptionItem[]
}

export interface QtyGroup extends BaseGroup {
  type: 'quantity_slider'
  qty_min: number | null
  qty_max: number | null
  qty_step: number | null
  qty_default: number | null
}

export interface RangeTier {
  from: number; to: number
  unit_price_cents: number
  label?: string
  min_block?: number
  multiplier?: number
  cap_cents?: number
}

export interface DoubleRangeGroup extends BaseGroup {
  type: 'double_range_slider'
  slider_min: number
  slider_max: number
  slider_step: number
  range_default_min: number | null
  range_default_max: number | null
  pricing_mode: 'flat' | 'tiered'
  unit_price_cents?: number
  base_fee_cents?: number
  max_span?: number | null
  tier_combine_strategy?: 'sum_piecewise' | 'highest_tier_only' | 'weighted_average'
  tiers?: RangeTier[]
}

export type AnyGroup = SelectorGroup | QtyGroup | DoubleRangeGroup

export type Selection =
  | number               // selector (single) id
  | number[]             // selector (multi) ids
  | { min: number; max: number } // range
  | null
  | number               // qty value