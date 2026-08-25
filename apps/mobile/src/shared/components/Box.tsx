import { View, ViewProps } from 'react-native';

export type BoxProps = ViewProps & {
  padding?: number;
  margin?: number;
  backgroundColor?: string;
};

export function Box({ style, padding, margin, backgroundColor, ...rest }: BoxProps) {
  return (
    <View 
      style={[
        padding !== undefined ? { padding } : undefined,
        margin !== undefined ? { margin } : undefined,
        backgroundColor ? { backgroundColor } : undefined,
        style
      ]} 
      {...rest} 
    />
  );
}
