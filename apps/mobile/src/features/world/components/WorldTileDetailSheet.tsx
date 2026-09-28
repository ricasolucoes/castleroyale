import { View } from 'react-native';
import type { WorldTile } from '@castleroyale/contracts';

import { Badge } from '@/shared/components/Badge';
import { BottomSheet } from '@/shared/components/BottomSheet';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export type TileDetailStatus = 'ready' | 'loading' | 'stale' | 'error' | 'empty';

export type WorldTileDetailSheetProps = {
  tile: WorldTile | null;
  status: TileDetailStatus;
  open: boolean;
  city?: { id: string; name_key: string; x: number; y: number; is_player_city: boolean } | null;
  onClose: () => void;
  onRetry: () => void;
  onReset: () => void;
};

export function WorldTileDetailSheet({
  tile,
  status,
  open,
  city = null,
  onClose,
  onRetry,
  onReset,
}: WorldTileDetailSheetProps) {
  const theme = useTheme();
  const { t } = useTranslation();

  return (
    <BottomSheet
      accessibilityViewIsModal
      enablePanDownToClose
      index={open ? 0 : -1}
      onClose={onClose}
      snapPoints={[`${theme.spacing['3xl']}%`]}
    >
      <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
        <Text accessibilityRole="header" variant="heading">
          {t('world.selected_tile')}
        </Text>
        {status === 'loading' && <Text>{t('world.tile_loading')}</Text>}
        {status === 'stale' && (
          <Card>
            <Text>{t('world.tile_stale')}</Text>
            <Button title={t('common.retry')} variant="secondary" onPress={onRetry} />
          </Card>
        )}
        {status === 'empty' && (
          <Card>
            <Text>{t('world.no_tiles')}</Text>
            <Button title={t('world.reset_to_city')} variant="secondary" onPress={onReset} />
          </Card>
        )}
        {status === 'error' && (
          <Card>
            <Text color={theme.color.danger}>{t('world.tile_error')}</Text>
            <Button title={t('common.retry')} variant="secondary" onPress={onRetry} />
          </Card>
        )}
        {status === 'ready' && tile && (
          <View style={{ gap: theme.spacing.sm }}>
            {city && (
              <View style={{ gap: theme.spacing.xs, marginBottom: theme.spacing.xs }}>
                <View style={{ flexDirection: 'row', gap: theme.spacing.xs }}>
                  <Badge
                    label={city.is_player_city ? t('world.your_city') : t('world.selected_city')}
                    variant={city.is_player_city ? 'warning' : 'neutral'}
                  />
                </View>
                <Text variant="heading">{t(city.name_key)}</Text>
              </View>
            )}
            <Text variant="label">{t('world.coordinates', { x: tile.x, y: tile.y })}</Text>
            <View style={{ flexDirection: 'row', gap: theme.spacing.sm }}>
              <Badge label={t('world.terrain', { terrain: tile.terrain })} variant="neutral" />
              <Badge label={t('world.region', { region: tile.region_id })} variant="neutral" />
            </View>
            <Text variant="caption" color={theme.color.text.secondary}>
              {t(`world.terrain_desc_${tile.terrain}`)}
            </Text>
          </View>
        )}
      </View>
    </BottomSheet>
  );
}
